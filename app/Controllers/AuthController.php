<?php

namespace App\Controllers;

use App\Models\StravaConfigModel; // Model to get Client ID/Secret
use App\Models\UserModel;         // Model to save user data
use Config\Services;              // To use HTTP Client, session, etc.

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

        // 2. Validate State (CSRF protection) and check for errors
        $storedState = session()->get('oauth_state');
        session()->remove('oauth_state'); // Remove state once used

        if (!empty($error)) {
            log_message('error', 'Strava callback error: ' . $error);
            return redirect()->to('/')->with('error', 'Strava authorization failed: ' . $error);
        }
        if (empty($code)) {
            log_message('error', 'Strava callback missing code.');
            return redirect()->to('/')->with('error', 'Strava authorization failed (missing code).');
        }
        if (empty($receivedState) || $receivedState !== $storedState) {
            log_message('error', 'Strava callback state mismatch.');
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
            // No base_uri needed here as we use the full URL below
            'timeout' => 10,
        ]);
        // Use the FULL URL for the token endpoint
        $tokenUrl = 'https://www.strava.com/api/v3/oauth/token';

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
            if (json_last_error() !== JSON_ERROR_NONE || !isset($tokenData->access_token) || !isset($tokenData->athlete)) {
                log_message('error', 'Failed to parse Strava token response: ' . json_last_error_msg() . ' | Body: ' . $body);
                return redirect()->to('/')->with('error', 'Error processing Strava response.');
            }

        } catch (\Exception $e) {
            // Log the full exception message and code
            log_message('error', 'Exception during Strava token exchange: (' . $e->getCode() . ') ' . $e->getMessage());
            return redirect()->to('/')->with('error', 'Could not connect to Strava. Check logs. (' . $e->getCode() . ')');
        }

        // 4. Save/Update User Data
        $userModel = new UserModel();
        $stravaAthlete = $tokenData->athlete; // Get athlete data from token response

        // Prepare data for the user model's findOrCreate method
        // Note: findOrCreate needs to handle inserting/updating based on strava_id
        $userId = $userModel->findOrCreate($stravaAthlete, $tokenData);

        if (!$userId) {
            log_message('error', 'Failed to save user data for Strava ID: ' . ($stravaAthlete->id ?? 'unknown'));
            return redirect()->to('/')->with('error', 'Failed to save user information.');
        }

        // 5. Set User Session
        $user = $userModel->find($userId); // Fetch the saved user data
        if (!$user) {
             log_message('error', 'Failed to retrieve newly saved user from DB. ID: ' . $userId);
             return redirect()->to('/')->with('error', 'Session setup error.');
        }

        $sessionData = [
            'user_id'             => $user->id, // Your app's user ID
            'strava_id'           => $user->strava_id,
            'firstname'           => $user->firstname,
            'isLoggedIn'          => true,
        ];
        session()->set($sessionData);
        log_message('info', 'User session created for user ID: ' . $userId);

        // 6. Redirect to Dashboard
        return redirect()->to('/dashboard'); // Or wherever logged-in users should go
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
