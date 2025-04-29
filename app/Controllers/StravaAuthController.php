<?php

namespace App\Controllers;

use App\Models\StravaConfigModel;
use App\Models\UserModel; // Import the UserModel
use CodeIgniter\Controller;
use Config\Services; // Import Services to use HTTP Client

class StravaAuthController extends BaseController // Extend BaseController for helpers like session, redirect
{
    /**
     * Initiates the Strava OAuth2 authorization flow.
     * Fetches credentials, builds the authorization URL, and redirects the user.
     */
    public function login()
    {
        print_r("<pre>this is sudarshan </pre>"); 
        // Load session helper if not autoloaded
        // helper('session'); // Usually autoloaded or available via BaseController

        // Instantiate the Strava configuration model
        $stravaConfigModel = new StravaConfigModel();

        // Fetch the 'default' Strava API credentials from the database
        $credentials = $stravaConfigModel->getCredentials('default');

        // --- Error Handling: Check if credentials were found ---
        if (!$credentials || empty($credentials->client_id) || empty($credentials->client_secret)) {
            log_message('error', 'Strava API credentials not found or incomplete in database.');
            // Display a user-friendly error page or message
            // You might want to create a specific error view
            return view('errors/html/error_general', [
                'heading' => 'Configuration Error',
                'message' => 'Strava application details are missing or invalid. Please contact the administrator.'
            ]);
            // Or redirect back with a flash message:
            // return redirect()->back()->with('error', 'Strava configuration is missing.');
        }

        // --- Credentials found, proceed with OAuth ---
        $clientId = $credentials->client_id;
        // $clientSecret = $credentials->client_secret; // Secret not needed for the initial redirect

        // --- Construct the Strava Authorization URL ---
        $stravaAuthorizeUrl = 'https://www.strava.com/oauth/authorize';

        // Define the URL where Strava should redirect back after authorization
        // IMPORTANT: This URL MUST be added to your Strava Application settings under "Authorization Callback Domain"
        $redirectUri = site_url('strava/callback'); // Generates full URL like http://yourdomain.com/strava/callback

        // Define the permissions (scopes) your application needs
        // 'read' is basic profile info. 'activity:read_all' allows reading all activities.
        // Adjust scope as needed for your application's features.
        $scope = 'read,activity:read_all';

        // Generate a random string for the 'state' parameter to prevent CSRF attacks
        $state = bin2hex(random_bytes(16));

        // Store the generated state value in the user's session
        // We will verify this when Strava redirects back to the callback URL
        session()->set('oauth2state', $state);

        // Build the query parameters for the authorization URL
        $authParams = [
            'client_id'     => $clientId,
            'redirect_uri'  => $redirectUri,
            'response_type' => 'code',          // We want an authorization code
            'approval_prompt' => 'auto',        // 'auto' or 'force'. 'auto' prompts only the first time.
            'scope'         => $scope,
            'state'         => $state,           // Include the CSRF protection token
        ];

        // Construct the full authorization URL
        $authUrl = $stravaAuthorizeUrl . '?' . http_build_query($authParams);

        // --- Redirect the user to Strava ---
        log_message('info', 'Redirecting user to Strava for authorization.');
        return redirect()->to($authUrl); // Use CodeIgniter's redirect helper
    }


    /**
     * Handles the callback from Strava after user authorization.
     * Verifies state, exchanges code for tokens, fetches athlete info,
     * finds/creates user, sets session, and redirects to dashboard.
     */
    public function callback()
    {
        // Get parameters from Strava callback
        $receivedState = $this->request->getGet('state');
        $code = $this->request->getGet('code');
        $scope = $this->request->getGet('scope'); // Scope actually granted by user
        $error = $this->request->getGet('error'); // Check if user denied access

        // Get stored state from session and remove it
        $storedState = session()->get('oauth2state');
        session()->remove('oauth2state');

        log_message('debug', 'Strava callback received. State: ' . $receivedState . ', Code: ' . $code . ', Scope: ' . $scope . ', Error: ' . $error);

        // --- 1. Check for errors or state mismatch ---
        if (!empty($error)) {
            log_message('error', 'Strava authorization error: ' . $error);
            // Redirect to login page with an error message
            return redirect()->to('/')->with('error', 'Strava authorization failed: ' . $error);
        }

        if (empty($code)) {
             log_message('error', 'Strava callback missing authorization code.');
            return redirect()->to('/')->with('error', 'Strava authorization failed: Missing code.');
        }

        if (empty($receivedState) || $receivedState !== $storedState) {
            log_message('error', 'Strava state parameter mismatch. Possible CSRF attack.');
            // Redirect to login page with an error message
            return redirect()->to('/')->with('error', 'Invalid security token. Please try logging in again.');
        }

        // --- 2. Exchange authorization code for tokens ---
        $stravaConfigModel = new StravaConfigModel();
        $credentials = $stravaConfigModel->getCredentials('default');

        if (!$credentials) {
            log_message('error', 'Strava credentials not found while processing callback.');
            return redirect()->to('/')->with('error', 'Configuration error. Please contact admin.');
        }

        // Use CodeIgniter's HTTP Client service
        $httpClient = Services::curlrequest([
            'base_uri' => 'https://www.strava.com/api/v3/',
             'timeout' => 10, // Set timeout for the request (increased slightly)
             'connect_timeout' => 5, // Connection timeout
        ]);

        $tokenUrl = 'oauth/token';
        try {
            $response = $httpClient->post($tokenUrl, [
                'form_params' => [
                    'client_id'     => $credentials->client_id,
                    'client_secret' => $credentials->client_secret,
                    'code'          => $code,
                    'grant_type'    => 'authorization_code',
                ],
                'http_errors' => false, // Prevent throwing exceptions on 4xx/5xx errors
            ]);

            $body = $response->getBody();
            $statusCode = $response->getStatusCode();
            log_message('debug', 'Strava token exchange response status: ' . $statusCode . ' Body: ' . $body);

            if ($statusCode !== 200) {
                 log_message('error', 'Strava token exchange failed. Status: ' . $statusCode . ' Response: ' . $body);
                 // Provide more specific error based on response if possible
                 $errorDetail = json_decode($body);
                 $errorMessage = 'Failed to authenticate with Strava. Please try again.';
                 if (isset($errorDetail->message)) {
                     $errorMessage .= ' (Error: ' . $errorDetail->message . ')';
                 }
                 return redirect()->to('/')->with('error', $errorMessage);
            }

            $tokenData = json_decode($body);

            // Check for JSON decoding errors and presence of essential token data
            if (json_last_error() !== JSON_ERROR_NONE || !isset($tokenData->access_token) || !isset($tokenData->refresh_token) || !isset($tokenData->expires_at) || !isset($tokenData->athlete)) {
                 log_message('error', 'Failed to parse Strava token response or missing data: ' . json_last_error_msg() . ' | Body: ' . $body);
                 return redirect()->to('/')->with('error', 'Error processing Strava response.');
            }

            // Add granted scope to token data for storage (if needed later)
            $tokenData->scope = $scope;

            // The athlete data is often included in the token response, use it directly!
            $stravaAthlete = $tokenData->athlete;


        } catch (\Exception $e) {
            log_message('error', 'Exception during Strava token exchange: ' . $e->getMessage());
            return redirect()->to('/')->with('error', 'Could not connect to Strava. Please try again later.');
        }

        /*
        // --- 3. Fetch athlete data using the access token (REDUNDANT if included in token response) ---
        // This step might be unnecessary if the athlete object is already in the token response
        // Keeping it commented out for reference in case the token response changes or lacks athlete data.
        try {
            $athleteResponse = $httpClient->get('athlete', [
                'headers' => [
                    'Authorization' => 'Bearer ' . $tokenData->access_token,
                ],
                 'http_errors' => false,
            ]);

            $athleteBody = $athleteResponse->getBody();
            $athleteStatusCode = $athleteResponse->getStatusCode();
             log_message('debug', 'Strava get athlete response status: ' . $athleteStatusCode . ' Body: ' . $athleteBody);


            if ($athleteStatusCode !== 200) {
                 log_message('error', 'Strava get athlete failed. Status: ' . $athleteStatusCode . ' Response: ' . $athleteBody);
                 return redirect()->to('/')->with('error', 'Failed to retrieve your Strava profile.');
            }

            $stravaAthlete = json_decode($athleteBody);

             if (json_last_error() !== JSON_ERROR_NONE || !isset($stravaAthlete->id)) {
                 log_message('error', 'Failed to parse Strava athlete response: ' . json_last_error_msg());
                 return redirect()->to('/')->with('error', 'Error processing Strava profile data.');
            }

        } catch (\Exception $e) {
            log_message('error', 'Exception during Strava get athlete: ' . $e->getMessage());
            return redirect()->to('/')->with('error', 'Could not retrieve profile from Strava. Please try again later.');
        }
        */

        // --- 4. Find or Create User in Database ---
        $userModel = new UserModel();
        // Pass the athlete object directly from the token response
        $userId = $userModel->findOrCreate($stravaAthlete, $tokenData);

        if (!$userId) {
            log_message('error', 'Failed to save user data to database for Strava ID: ' . ($stravaAthlete->id ?? 'unknown'));
            return redirect()->to('/')->with('error', 'Failed to save your user information. Please contact support.');
        }

        // --- 5. Set User Session ---
        // Fetch the full user record to ensure we have the latest data (optional but good practice)
        $user = $userModel->find($userId);
        if (!$user) {
             log_message('error', 'Failed to retrieve newly saved user from DB. ID: ' . $userId);
             return redirect()->to('/')->with('error', 'Session setup error. Please try logging in again.');
        }

        $sessionData = [
            'user_id'             => $user->id, // Your application's user ID
            'strava_id'           => $user->strava_id,
            'firstname'           => $user->firstname,
            'lastname'            => $user->lastname,
            'profile_picture_url' => $user->profile_picture_url,
            'isLoggedIn'          => true,
            // Avoid storing tokens in session unless absolutely necessary.
        ];
        session()->set($sessionData);
        log_message('info', 'User session created for user ID: ' . $userId . ', Strava ID: ' . $user->strava_id);


        // --- 6. Redirect to Dashboard ---
        log_message('info', 'Redirecting user ID ' . $userId . ' to dashboard.');
        return redirect()->to('/dashboard'); // Redirect to the user's dashboard page
    }
}
