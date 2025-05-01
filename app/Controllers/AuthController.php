<?php

namespace App\Controllers;

use App\Models\StravaConfigModel; // Model to get Client ID/Secret
use App\Models\UserModel;         // Model to save user data
use App\Models\ActivityModel;     // Model to save activity data
use Config\Services;              // To use HTTP Client, session, etc.
use CodeIgniter\I18n\Time;        // To handle time/dates for fetching activities

class AuthController extends BaseController
{
    /**
     * Step 1: Redirect user to Strava for authorization.
     */
    public function login()
    {
        // 1. Get Strava App Credentials
        $stravaConfigModel = new StravaConfigModel();
        $credentials = $stravaConfigModel->getCredentials('default');

        // Basic check for credentials
        if (!$credentials || empty($credentials->client_id)) {
            log_message('error', 'Strava Client ID not found in database.');
            // Show a simple error or redirect back with a message
            return redirect()->to('/')->with('error', 'Strava configuration is missing. Please contact admin.');
        }
        $clientId = $credentials->client_id;

        // 2. Prepare Strava Authorization URL
        $stravaAuthorizeUrl = 'https://www.strava.com/oauth/authorize';
        $redirectUri = site_url('auth/callback'); // The URL for Step 2 (callback method)
        $scope = 'read,activity:read_all';      // Request basic profile and activity read permissions
        $state = bin2hex(random_bytes(16));     // Security token (CSRF protection)

        // Store state in session to verify in callback
        session()->set('oauth_state', $state);

        $authParams = [
            'client_id'     => $clientId,
            'redirect_uri'  => $redirectUri,
            'response_type' => 'code', // We want an authorization code from Strava
            'approval_prompt' => 'auto', // Prompt only if needed
            'scope'         => $scope,
            'state'         => $state,
        ];
        $authUrl = $stravaAuthorizeUrl . '?' . http_build_query($authParams);

        // 3. Redirect the user
        log_message('info', 'Redirecting user to Strava for authorization.');
        return redirect()->to($authUrl);
    }

    /**
     * Step 2: Handle the callback from Strava after authorization.
     */
    public function callback()
    {
        // 1. Get parameters from Strava's redirect
        $code = $this->request->getGet('code');
        $receivedState = $this->request->getGet('state');
        $error = $this->request->getGet('error');
        $scope = $this->request->getGet('scope'); // Scope granted by user

        // 2. Validate State (CSRF protection) and check for errors
        $storedState = session()->get('oauth_state');
        session()->remove('oauth_state'); // Remove state once used

        log_message('debug', "Callback received. Code: {$code}, State: {$receivedState}, Scope: {$scope}, Error: {$error}");

        if (!empty($error)) {
            log_message('error', 'Strava callback error: ' . $error);
            return redirect()->to('/')->with('error', 'Strava authorization failed: ' . $error);
        }
        if (empty($code)) {
            log_message('error', 'Strava callback missing code.');
            return redirect()->to('/')->with('error', 'Strava authorization failed (missing code).');
        }
        if (empty($receivedState) || $receivedState !== $storedState) {
            log_message('error', 'Strava callback state mismatch. Received: ' . $receivedState . ' Expected: ' . $storedState);
            return redirect()->to('/')->with('error', 'Invalid security token. Please try logging in again.');
        }

        // 3. Exchange Code for Tokens
        $stravaConfigModel = new StravaConfigModel();
        $credentials = $stravaConfigModel->getCredentials('default');
        if (!$credentials || empty($credentials->client_id) || empty($credentials->client_secret)) {
            log_message('error', 'Strava credentials missing during token exchange.');
            return redirect()->to('/')->with('error', 'Configuration error during login.');
        }

        // Use CodeIgniter's HTTP Client service
        $httpClient = Services::curlrequest([
            'timeout' => 15, // Increased timeout slightly for potentially slower network
        ]);
        // Use the FULL URL for the token endpoint
        $tokenUrl = 'https://www.strava.com/api/v3/oauth/token';
        $accessToken = null; // Initialize access token variable
        $tokenData = null;   // Initialize token data variable

        try {
            log_message('debug', 'Attempting POST to Strava token URL: ' . $tokenUrl);
            // Make POST request using the absolute $tokenUrl
            $response = $httpClient->post($tokenUrl, [
                'form_params' => [
                    'client_id'     => $credentials->client_id,
                    'client_secret' => $credentials->client_secret,
                    'code'          => $code,
                    'grant_type'    => 'authorization_code',
                ],
                'http_errors' => false, // Don't throw exceptions for 4xx/5xx
            ]);

            $statusCode = $response->getStatusCode();
            $body = $response->getBody();
            log_message('debug', 'Strava token response status: ' . $statusCode . ' Body: ' . $body);

            if ($statusCode !== 200) {
                log_message('error', 'Strava token exchange failed. Status: ' . $statusCode . ' Body: ' . $body);
                return redirect()->to('/')->with('error', 'Failed to get tokens from Strava. Status: ' . $statusCode);
            }

            $tokenData = json_decode($body);
            // Check for JSON errors and essential data presence
            if (json_last_error() !== JSON_ERROR_NONE || !isset($tokenData->access_token) || !isset($tokenData->athlete) || !isset($tokenData->refresh_token) || !isset($tokenData->expires_at)) {
                log_message('error', 'Failed to parse Strava token response or missing essential data: ' . json_last_error_msg() . ' | Body: ' . $body);
                return redirect()->to('/')->with('error', 'Error processing Strava response.');
            }
            $accessToken = $tokenData->access_token; // Store access token for activity fetch
            $tokenData->scope = $scope; // Add granted scope to token data

        } catch (\Exception $e) {
            // Log the full exception message and code
            log_message('error', 'Exception during Strava token exchange: (' . $e->getCode() . ') ' . $e->getMessage());
            return redirect()->to('/')->with('error', 'Could not connect to Strava. Check logs. (' . $e->getCode() . ')');
        }

        // 4. Save/Update User Data
        $userModel = new UserModel();
        $stravaAthlete = $tokenData->athlete; // Get athlete data from token response

        // Pass the athlete object and full token data to findOrCreate
        $userId = $userModel->findOrCreate($stravaAthlete, $tokenData);

        if (!$userId) {
            log_message('error', 'Failed to save user data for Strava ID: ' . ($stravaAthlete->id ?? 'unknown'));
            return redirect()->to('/')->with('error', 'Failed to save user information.');
        }

        // --- 5. Fetch and Sync Activities ---
        // Check if activity scope was granted and we have a token
        // Use str_contains for PHP 8+ or strpos for PHP 7
        $activityScopeGranted = $scope && (function_exists('str_contains') ? str_contains($scope, 'activity:read') : strpos($scope, 'activity:read') !== false);

        if ($accessToken && $activityScopeGranted) {
            $activityModel = new ActivityModel();
            $activitiesApiUrl = 'https://www.strava.com/api/v3/athlete/activities';

            // Define time range for fetching activities (e.g., last 30 days)
            // Adjust 'before' and 'after' as needed for your challenge logic
            $fetchBefore = Time::now()->getTimestamp(); // Current time
            $fetchAfter = Time::now()->subDays(365)->getTimestamp(); // 30 days ago (adjust as needed)

            // Strava API allows fetching up to 200 activities per page
            $perPage = 100; // Fetch 100 activities per request (adjust as needed)
            $page = 1;
            $allActivities = [];

            log_message('info', "Fetching activities for user ID {$userId} between " . Time::createFromTimestamp($fetchAfter)->toDateTimeString() . " and " . Time::createFromTimestamp($fetchBefore)->toDateTimeString());

            // Loop to fetch pages of activities until no more are returned
            do {
                $fetchedActivities = []; // Reset for each page
                try {
                    $activityResponse = $httpClient->get($activitiesApiUrl, [
                        'headers' => [
                            'Authorization' => 'Bearer ' . $accessToken,
                        ],
                        'query' => [
                            'before'   => $fetchBefore,
                            'after'    => $fetchAfter,
                            'page'     => $page,
                            'per_page' => $perPage,
                        ],
                        'http_errors' => false,
                    ]);

                    $activityStatusCode = $activityResponse->getStatusCode();
                    $activityBody = $activityResponse->getBody();

                    if ($activityStatusCode === 200) {
                        $fetchedActivities = json_decode($activityBody);
                        if (json_last_error() === JSON_ERROR_NONE && is_array($fetchedActivities)) {
                            $allActivities = array_merge($allActivities, $fetchedActivities);
                            log_message('debug', "Fetched page {$page} with " . count($fetchedActivities) . " activities for user ID {$userId}.");
                            $page++; // Prepare for next page
                        } else {
                            log_message('error', "Failed to parse activities response (page {$page}) for user ID {$userId}. Body: " . $activityBody);
                            $fetchedActivities = []; // Stop fetching if parse error
                        }
                    } elseif ($activityStatusCode === 401 || $activityStatusCode === 403) {
                         log_message('error', "Authorization error fetching activities (page {$page}) for user ID {$userId}. Status: {$activityStatusCode}. Token might be invalid or scope insufficient.");
                         $fetchedActivities = []; // Stop fetching on auth errors
                    } else {
                        log_message('error', "Failed to fetch activities (page {$page}) for user ID {$userId}. Status: {$activityStatusCode}, Body: " . $activityBody);
                        $fetchedActivities = []; // Stop fetching on other errors
                    }
                } catch (\Exception $e) {
                    log_message('error', "Exception fetching activities (page {$page}) for user ID {$userId}: " . $e->getMessage());
                    $fetchedActivities = []; // Stop fetching on exception
                }
                // Add a small delay to be kind to the API, especially if fetching many pages
                if (!empty($fetchedActivities)) {
                    usleep(200000); // 200 milliseconds delay
                }
            } while (!empty($fetchedActivities) && count($fetchedActivities) === $perPage); // Continue if last page was full

            // Sync all fetched activities to the database
            if (!empty($allActivities)) {
                $syncResults = $activityModel->syncActivities($userId, $allActivities);
                // Optional: Store sync results in flashdata if needed
                // session()->setFlashdata('sync_results', $syncResults);
            } else {
                 log_message('info', "No new activities found to sync for user ID {$userId} in the specified range.");
            }

        } else {
            log_message('warning', 'Skipping activity sync because access token was not obtained or activity:read scope not granted for user ID ' . $userId . '. Scope: ' . $scope);
        }


        // --- 6. Set User Session ---
        $user = $userModel->find($userId); // Re-fetch user data to ensure it's current
        if (!$user) {
             log_message('error', 'Failed to retrieve newly saved user from DB. ID: ' . $userId);
             return redirect()->to('/')->with('error', 'Session setup error.');
        }

        // Set session data needed for the application
        $sessionData = [
            'user_id'             => $user->id, // Your app's user ID
            'strava_id'           => $user->strava_id,
            'firstname'           => $user->firstname,
            'lastname'            => $user->lastname,
            'profile_picture_url' => $user->profile_picture_url,
            'isLoggedIn'          => true,
        ];
        session()->set($sessionData);
        log_message('info', 'User session created for user ID: ' . $userId . ' after activity sync attempt.');

        // --- 7. Redirect to Dashboard ---
        return redirect()->to('/dashboard'); // Redirect to the user's dashboard page
    }

    /**
     * Simple Logout.
     */
    public function logout()
    {
        session()->destroy();
        return redirect()->to('/')->with('message', 'You have been logged out.');
    }
}
