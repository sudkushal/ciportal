<?php

namespace App\Controllers;

use App\Models\UserModel; // Make sure UserModel exists and is correctly namespaced
use CodeIgniter\Controller;
use Config\Services; // Needed for HTTP Client

class AuthController extends Controller
{
    /**
     * Display the login page.
     * Redirects to dashboard if already logged in.
     */
    public function login()
    {
        log_message('debug', 'Login page requested.');
        // Redirect if user is already logged into the session
        if (session()->get('isLoggedIn')) {
            log_message('debug', 'User already logged in, redirecting to dashboard.');
            return redirect()->to('/dashboard');
        }
        // Assumes you have a view file at app/Views/auth/login.php
        return view('auth/login');
    }

    /**
     * Redirect the user to Strava's authorization page to grant permissions.
     */
    public function stravaRedirect()
    {
        log_message('debug', 'Redirecting user to Strava for authorization.');

        // Use the defined constant
        // Ensure the constant exists and is not the placeholder value
        if (!defined('STRAVA_CLIENT_ID') || !STRAVA_CLIENT_ID || STRAVA_CLIENT_ID === 'YOUR_ACTUAL_CLIENT_ID_FROM_STRAVA') {
            log_message('critical', 'STRAVA_CLIENT_ID constant is not defined or not set correctly in app/Config/Constants.php.');
            return redirect()->to('/login')->with('error', 'Strava integration is not configured correctly. Please contact support.');
        }
        $clientId = STRAVA_CLIENT_ID;

        // Define the callback URL for the *Authorization* (login) flow, matching the route for stravaCallback()
        $redirectUri = base_url('callback'); // Generates URL like http://yourdomain.com/callback

        // Define the scopes (permissions) your application needs.
        $scopes = 'read,profile:read_all,activity:read_all';

        // Construct the Strava authorization URL
        $stravaAuthUrl = "https://www.strava.com/oauth/authorize?" . http_build_query([
            'client_id'       => $clientId, // Use constant value
            'response_type'   => 'code', // We want an authorization code
            'redirect_uri'    => $redirectUri, // Where Strava sends the user back
            'scope'           => $scopes, // Permissions requested
            'approval_prompt' => 'auto' // 'auto' = prompt only if needed, 'force' = always prompt
        ]);

        // Redirect the user's browser to the Strava URL
        log_message('debug', 'Redirecting to: ' . $stravaAuthUrl);
        return redirect()->to($stravaAuthUrl);
    }


    /**
     * Handle the callback from Strava after user authorization.
     * 1. Exchanges the authorization code for access/refresh tokens.
     * 2. Fetches athlete info.
     * 3. Creates or updates the user in the local database.
     * 4. Attempts to register the application's webhook subscription via API.
     * 5. Creates a user session (logs the user in).
     * 6. Redirects to the dashboard.
     */
    public function stravaCallback()
    {
        log_message('debug', 'Received callback from Strava authorization.');

        // Retrieve parameters Strava sends back
        $code = $this->request->getGet('code');   // The authorization code (if successful)
        $error = $this->request->getGet('error'); // An error message (if authorization failed or was denied)
        $scope = $this->request->getGet('scope'); // The scopes actually granted by the user

        // Handle authorization errors from Strava
        if ($error) {
            log_message('error', "Strava authorization error received in callback: {$error}");
            return redirect()->to('/login')->with('error', 'Strava authorization failed: ' . ucfirst(str_replace('_', ' ', $error)));
        }

        // Handle missing authorization code
        if (!$code) {
            log_message('error', 'Strava callback missing authorization code.');
            return redirect()->to('/login')->with('error', 'Authorization code not found from Strava. Please try logging in again.');
        }

        log_message('debug', "Strava callback successful. Code: {$code}, Granted Scope: {$scope}");

        // --- Step 1 & 2: Exchange Code for Tokens and Fetch Athlete Info ---
        // Use defined constants
        if (!defined('STRAVA_CLIENT_ID') || !STRAVA_CLIENT_ID || STRAVA_CLIENT_ID === 'YOUR_ACTUAL_CLIENT_ID_FROM_STRAVA' ||
            !defined('STRAVA_CLIENT_SECRET') || !STRAVA_CLIENT_SECRET || STRAVA_CLIENT_SECRET === 'YOUR_ACTUAL_CLIENT_SECRET_FROM_STRAVA') {
             log_message('critical', 'STRAVA_CLIENT_ID or STRAVA_CLIENT_SECRET constants are not defined or not set correctly in app/Config/Constants.php.');
             return redirect()->to('/login')->with('error', 'Strava integration is missing server configuration.');
        }
        $clientId = STRAVA_CLIENT_ID;
        $clientSecret = STRAVA_CLIENT_SECRET;


        // Use CodeIgniter's HTTP Client service
        $client = Services::curlrequest(['timeout' => 15]); // Set a reasonable timeout

        try {
            // Make POST request to Strava's token endpoint
            $response = $client->post('https://www.strava.com/oauth/token', [
                 'form_params' => [
                    'client_id'     => $clientId, // Use constant value
                    'client_secret' => $clientSecret, // Use constant value
                    'code'          => $code, // The code received from Strava
                    'grant_type'    => 'authorization_code', // Specify the grant type
                ],
                'http_errors' => false // Prevent exceptions on 4xx/5xx, handle manually
            ]);

            $statusCode = $response->getStatusCode();
            $body = $response->getBody();
            $data = json_decode($body, true); // Decode JSON response to array

            // Check for errors during token exchange
            if ($statusCode !== 200 || !isset($data['access_token']) || !isset($data['athlete'])) {
                 log_message('error', "Failed to obtain Strava tokens or athlete data. Status: {$statusCode}, Response: {$body}");
                 // Provide a user-friendly error
                 $errorMsg = isset($data['message']) ? $data['message'] : 'Failed to connect with Strava.';
                 return redirect()->to('/login')->with('error', 'Error connecting to Strava: ' . $errorMsg);
             }

            log_message('debug', 'Successfully obtained tokens and athlete data from Strava.');

            // --- Step 3: Process Athlete Data and Store/Update User in Database ---
            $athlete = $data['athlete']; // Athlete object from Strava response
            $userModel = new UserModel(); // Instantiate your user model

            // Prepare data for database insertion/update
            $userData = [
                'strava_id'     => $athlete['id'],
                'firstname'     => $athlete['firstname'] ?? '',
                'lastname'      => $athlete['lastname'] ?? '',
                'profile_pic'   => $athlete['profile_medium'] ?? $athlete['profile'] ?? null,
                'access_token'  => $data['access_token'],
                'refresh_token' => $data['refresh_token'],
                'expires_at'    => $data['expires_at'],
                'strava_scopes' => $scope,
                'is_strava_authorized' => true
             ];

            // Check if this Strava user already exists in your database
            $user = $userModel->where('strava_id', $athlete['id'])->first();

            if (!$user) {
                // User doesn't exist, create a new record
                 log_message('info', "Creating new user profile for Strava ID: {$athlete['id']}");
                 $userId = $userModel->insert($userData);
                 if (!$userId) {
                     log_message('error', 'Failed to insert new user into database. Errors: ' . json_encode($userModel->errors()));
                     return redirect()->to('/login')->with('error', 'Failed to create your user profile. Please try again.');
                 }
                 $user = $userModel->find($userId);
                 if (!$user) {
                      log_message('error', 'Failed to fetch newly created user from database. User ID: ' . $userId);
                      return redirect()->to('/login')->with('error', 'Error retrieving user profile after creation.');
                 }
                 log_message('info', "New user created successfully. Local ID: {$user['id']}");

             } else {
                // User exists, update their record
                 log_message('info', "Updating existing user profile for Strava ID: {$athlete['id']}, Local ID: {$user['id']}");
                 if (!$userModel->update($user['id'], $userData)) {
                      log_message('error', "Failed to update user ID: {$user['id']} in database. Errors: " . json_encode($userModel->errors()));
                 } else {
                      log_message('info', "User profile updated successfully. Local ID: {$user['id']}");
                 }
                 $user = array_merge($user, $userData);
             }

            // --- Step 4: Attempt to Register Webhook Subscription ---
            $webhookRegistered = $this->_registerWebhookSubscription(); // This function now uses constants
            if ($webhookRegistered) {
                log_message('info', 'Webhook registration attempt completed successfully (or already existed).');
            } else {
                log_message('warning', 'Webhook registration attempt failed. Check previous logs for details. Login proceeding.');
            }


            // --- Step 5: Login the User (Set Session Data) ---
            session()->set([
                 'id'          => $user['id'],
                 'strava_id'   => $user['strava_id'],
                 'firstname'   => $user['firstname'],
                 'lastname'    => $user['lastname'],
                 'profile_pic' => $user['profile_pic'],
                 'isLoggedIn'  => true,
             ]);

            log_message('info', "User session created successfully. User ID: {$user['id']}, Strava ID: {$user['strava_id']}");

            // --- Step 6: Redirect to Dashboard ---
            return redirect()->to('/dashboard');

        } catch (\Exception $e) {
            log_message('error', 'Exception during Strava callback processing: ' . $e->getMessage() . "\n" . $e->getTraceAsString());
            return redirect()->to('/login')->with('error', 'An unexpected server error occurred during login. Please try again later.');
        }
    }

    /**
     * Attempt to create or verify the Strava webhook subscription via API using constants.
     * Makes a POST request to https://www.strava.com/api/v3/push_subscriptions
     *
     * @return bool True if the subscription was created, already exists, or updated successfully, False otherwise.
     */
    private function _registerWebhookSubscription(): bool
    {
        log_message('info', 'Attempting to register/verify Strava webhook subscription via API using constants.');

        // --- Use defined constants and validate them ---
        if (!defined('STRAVA_CLIENT_ID') || !STRAVA_CLIENT_ID || STRAVA_CLIENT_ID === 'YOUR_ACTUAL_CLIENT_ID_FROM_STRAVA' ||
            !defined('STRAVA_CLIENT_SECRET') || !STRAVA_CLIENT_SECRET || STRAVA_CLIENT_SECRET === 'YOUR_ACTUAL_CLIENT_SECRET_FROM_STRAVA' ||
            !defined('STRAVA_WEBHOOK_CALLBACK_URL') || !STRAVA_WEBHOOK_CALLBACK_URL || STRAVA_WEBHOOK_CALLBACK_URL === 'YOUR_PUBLIC_HTTPS_WEBHOOK_URL' ||
            !defined('STRAVA_VERIFY_TOKEN') || !STRAVA_VERIFY_TOKEN || STRAVA_VERIFY_TOKEN === 'YOUR_SECRET_VERIFY_TOKEN_YOU_CREATED') {
             log_message('error', 'Webhook registration failed: One or more required Strava constants are not defined or not set correctly in app/Config/Constants.php.');
             return false; // Cannot proceed without config
        }
        $clientId = STRAVA_CLIENT_ID;
        $clientSecret = STRAVA_CLIENT_SECRET;
        $webhookCallbackUrl = STRAVA_WEBHOOK_CALLBACK_URL;
        $webhookVerifyToken = STRAVA_VERIFY_TOKEN;


        // --- Prepare API Request ---
        $client = Services::curlrequest(['timeout' => 15]); // Use CodeIgniter's HTTP client
        $stravaApiEndpoint = 'https://www.strava.com/api/v3/push_subscriptions';

        try {
            // Send POST request to create the subscription
            log_message('debug', "Sending POST to {$stravaApiEndpoint} with callback_url: {$webhookCallbackUrl}");
            $response = $client->post($stravaApiEndpoint, [
                'form_params' => [
                    'client_id'     => $clientId, // Use constant value
                    'client_secret' => $clientSecret, // Use constant value
                    'callback_url'  => $webhookCallbackUrl, // Use constant value
                    'verify_token'  => $webhookVerifyToken, // Use constant value
                ],
                'http_errors' => false // Disable throwing exceptions for 4xx/5xx responses
            ]);

            $statusCode = $response->getStatusCode();
            $body = $response->getBody();
            $responseData = json_decode($body, true); // Decode response body

            // --- Handle API Response ---
            if ($statusCode === 201) {
                // HTTP 201 Created: Successfully created a NEW subscription
                $subscriptionId = $responseData['id'] ?? 'N/A';
                log_message('info', "Successfully CREATED Strava webhook subscription via API using constants. Subscription ID: {$subscriptionId}");
                return true; // Success
            } elseif ($statusCode === 200) {
                 // HTTP 200 OK: Likely indicates existing/updated subscription
                 log_message('info', "Strava webhook subscription endpoint returned 200 OK (likely indicates existing/updated subscription) using constants. Response: {$body}");
                 return true; // Treat as success
            } else {
                // Handle API errors
                $errorMessage = $responseData['message'] ?? 'Unknown error';
                $errors = isset($responseData['errors']) ? json_encode($responseData['errors']) : 'N/A';
                log_message('error', "Failed to create/verify Strava webhook subscription via API using constants. Status: {$statusCode}, Message: {$errorMessage}, Errors: {$errors}, Response Body: {$body}");

                // Check specifically if the error indicates the subscription already exists
                if (strpos($body, 'callback url already subscribed') !== false || (isset($responseData['errors']) && isset($responseData['errors'][0]['code']) && $responseData['errors'][0]['code'] === 'already exists')) {
                     log_message('info', 'Webhook subscription already exists for this callback URL according to API response.');
                     return true; // Treat as success
                }
                return false; // Indicate failure for other errors
            }

        } catch (\Exception $e) {
            // Catch exceptions during the HTTP request itself
            log_message('error', 'Exception during Strava webhook subscription API call using constants: ' . $e->getMessage());
            return false; // Indicate failure
        }
    }


    /**
     * Log the user out by destroying their session data.
     */
    public function logout()
    {
        $userId = session()->get('id'); // Get user ID before destroying session for logging
        log_message('info', 'User logging out. User ID: ' . ($userId ?? 'N/A'));

        // Destroy all session data
        session()->destroy();

        // Redirect to login page with a success message
        return redirect()->to('/login')->with('message', 'You have been successfully logged out.');
    }
}
