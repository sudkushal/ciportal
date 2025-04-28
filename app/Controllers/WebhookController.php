<?php

namespace App\Controllers;

use App\Models\UserModel; // <-- Import your UserModel
use App\Models\ActivityModel; // <-- IMPORT YOUR ACTIVITY MODEL HERE
use CodeIgniter\Controller;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;
use Psr\Log\LoggerInterface;
use Config\Services; // <-- Import Services to use HTTP client

class WebhookController extends Controller
{
    private $stravaVerifyToken;
    private $userModel;
    private $activityModel; // <-- Property to hold ActivityModel instance

    public function initController(RequestInterface $request, ResponseInterface $response, LoggerInterface $logger)
    {
        parent::initController($request, $response, $logger);

        // Load the verify token from environment variables (.env file)
        // Ensure STRAVA_VERIFY_TOKEN is set in your .env
        $this->stravaVerifyToken = getenv('STRAVA_VERIFY_TOKEN') ?: 'YOUR_FALLBACK_VERIFY_TOKEN';

        // Instantiate Models
        $this->userModel = new UserModel();
        // Ensure ActivityModel exists and is correctly namespaced
        try {
             $this->activityModel = new ActivityModel(); // <-- CREATE INSTANCE OF ACTIVITY MODEL
        } catch (\Throwable $e) {
             log_message('error', 'Failed to instantiate ActivityModel: ' . $e->getMessage());
             // Handle error appropriately - maybe throw exception or log and exit
             // For now, we'll log and allow potential errors later if $this->activityModel is null
             $this->activityModel = null;
        }


        // IMPORTANT: CSRF protection MUST be disabled for the webhook route.
        // This is done in app/Config/Filters.php by adding the route URI
        // (e.g., 'webhook/strava') to the 'except' array for the 'csrf' filter.
    }

    /**
     * Handles incoming Strava Webhook requests (both GET for verification and POST for events)
     * Route: Define in app/Config/Routes.php -> $routes->match(['get', 'post'], 'webhook/strava', 'WebhookController::handleStrava');
     */
    public function handleStrava()
    {
        log_message('debug', 'Webhook handleStrava method accessed. Method: ' . $this->request->getMethod());

        // --- 1. Handle Subscription Verification (GET Request) ---
        if ($this->request->getMethod() === 'get') {
            log_message('info', 'Received Strava Webhook GET request for verification.');
            $mode = $this->request->getGet('hub.mode');
            $challenge = $this->request->getGet('hub.challenge');
            $token = $this->request->getGet('hub.verify_token');

            log_message('debug', "Verification Params: Mode='{$mode}', Token='{$token}', Challenge='{$challenge}'");
            log_message('debug', "Expected Token: '{$this->stravaVerifyToken}'");

            // Check if the mode is 'subscribe' and the token matches the one set in Strava and .env
            if ($mode === 'subscribe' && $token === $this->stravaVerifyToken) {
                log_message('info', 'Strava Webhook Verification successful. Responding with challenge.');
                // Respond with the challenge value provided by Strava
                return $this->response->setStatusCode(200)->setJSON(['hub.challenge' => $challenge]);
            } else {
                log_message('error', 'Strava Webhook Verification failed. Mode: '.$mode.' Token Received: '.$token);
                // Respond with 403 Forbidden if verification fails
                return $this->response->setStatusCode(403)->setBody('Verification Failed');
            }
        }

        // --- 2. Handle Event Notification (POST Request) ---
        if ($this->request->getMethod() === 'post') {
            log_message('info', 'Received Strava Webhook POST request (event notification).');
            // Attempt to get the JSON payload
            $input = $this->request->getJSON();

            // Validate that we received JSON data
            if (!$input) {
                log_message('error', 'Failed to parse Strava Webhook JSON payload or payload is empty. Raw body: ' . $this->request->getBody());
                // Still respond 200 OK to prevent Strava retries, but log the error.
                return $this->response->setStatusCode(200)->setBody('EVENT_RECEIVED_BUT_INVALID_PAYLOAD');
            }

            // Log the received data for debugging (consider logging level in production)
            log_message('info', 'Strava Webhook Data: ' . json_encode($input, JSON_PRETTY_PRINT));

            // --- 3. Acknowledge Receipt Immediately (Crucial!) ---
            // Strava expects a 200 OK response within 2 seconds.
             $this->response->setStatusCode(200)->setBody('EVENT_RECEIVED')->send();
             // Try to ensure the response is sent before continuing script execution
             if (ob_get_level() > 0) { ob_end_flush(); }
             flush();
             // If using PHP-FPM, this helps release the connection
             if (function_exists('fastcgi_finish_request')) {
                 fastcgi_finish_request();
             }

            // --- 4. Process the Event Data (Update Local Database) ---
            // This part runs *after* the 200 OK has been sent to Strava.
            try {
                // Extract key information from the payload
                $objectType = $input->object_type ?? null;
                $aspectType = $input->aspect_type ?? null;
                $objectId   = $input->object_id ?? null;   // Activity ID or Athlete ID
                $ownerId    = $input->owner_id ?? null;    // Athlete ID (the user the event belongs to)
                $updates    = $input->updates ?? null;    // Details of changes for 'update' aspect_type

                // Validate essential data
                if (!$ownerId || !$objectType || !$aspectType || !$objectId) {
                    log_message('error', 'Webhook payload missing essential fields (owner_id, object_type, aspect_type, object_id). Payload: ' . json_encode($input));
                    exit(); // Stop processing if critical data is missing
                }

                 // Check if ActivityModel was loaded correctly
                if (!$this->activityModel && $objectType === 'activity') {
                     log_message('critical', 'ActivityModel not loaded, cannot process activity webhook.');
                     exit();
                }


                log_message('debug', "Processing Event: ObjectType='{$objectType}', AspectType='{$aspectType}', ObjectID='{$objectId}', OwnerID='{$ownerId}'");

                // Find the user in your local database using the Strava Athlete ID (ownerId)
                $user = $this->userModel->where('strava_id', $ownerId)->first();

                // If the user doesn't exist in your system, log it and stop.
                if (!$user) {
                    log_message('error', "Webhook received for unknown Strava user ID (owner_id): {$ownerId}");
                    exit();
                }

                // --- Route processing based on event type ---
                if ($objectType === 'activity') {
                    // Ensure objectId is treated as an integer for activity events
                    $stravaActivityId = (int) $objectId;

                    if ($aspectType === 'create') {
                        // Fetch details and INSERT into local ActivityModel
                        $this->processNewActivity($user, $stravaActivityId);
                    } elseif ($aspectType === 'update') {
                         // Fetch details and UPDATE local ActivityModel record
                        $this->processUpdatedActivity($user, $stravaActivityId, $updates);
                    } elseif ($aspectType === 'delete') {
                        // DELETE record from local ActivityModel
                        $this->processDeletedActivity($user, $stravaActivityId);
                    } else {
                         log_message('warning', "Unhandled aspect_type '{$aspectType}' for object_type 'activity'.");
                    }
                } elseif ($objectType === 'athlete') {
                    // Check for deauthorization event
                    if ($aspectType === 'update' && isset($updates->authorized) && $updates->authorized === false) {
                         // UPDATE user in UserModel (clear tokens, mark as deauthorized)
                         log_message('warning', "Athlete {$ownerId} deauthorized the application via webhook update.");
                         $this->processDeauthorization($user);
                     } else {
                          log_message('info', "Received athlete update webhook (aspect: {$aspectType}). Updates: " . json_encode($updates));
                          // Handle other athlete updates if needed (e.g., weight changes if subscribed)
                     }
                } else {
                     log_message('warning', "Unhandled object_type '{$objectType}' received in webhook.");
                }

                log_message('info', "Finished processing webhook for Strava User ID: {$ownerId}.");
                exit(); // Explicitly exit after successful processing

            } catch (\Exception $e) {
                // Catch any exceptions during processing *after* the 200 OK was sent
                log_message('error', 'Error processing Strava webhook event after acknowledgement: ' . $e->getMessage() . "\n" . $e->getTraceAsString());
                exit(); // Exit after logging error
            }
        }

        // --- Invalid HTTP Method ---
        // If the request method is neither GET nor POST
        log_message('warning', 'Received Strava Webhook request with invalid method: ' . $this->request->getMethod());
        return $this->response->setStatusCode(405)->setBody('Method Not Allowed'); // 405 Method Not Allowed
    }

    // ========================================================================
    // Processing Functions - Implement logic to modify LOCAL DATABASE
    // ========================================================================

    /**
     * Process a new activity: Fetch details and INSERT into local ActivityModel.
     * @param array $user User data from your UserModel
     * @param int $stravaActivityId The ID of the new Strava activity
     */
    private function processNewActivity(array $user, int $stravaActivityId) {
        log_message('info', "Processing Strava activity CREATE for User ID: {$user['id']}, Strava Activity ID: {$stravaActivityId}");

        // Defend against duplicate processing (e.g., from Strava retries)
        $existingActivity = $this->activityModel
                                ->where('strava_activity_id', $stravaActivityId)
                                ->where('user_id', $user['id']) // Check against your internal user ID
                                ->first();
        if ($existingActivity) {
            log_message('warning', "Activity {$stravaActivityId} already exists locally for User ID {$user['id']}. Skipping insert. Treating as update instead.");
            // It might be an update that arrived slightly out of order, or a retry.
            // Call update logic to ensure data consistency.
            $this->processUpdatedActivity($user, $stravaActivityId, null); // Pass null for updates as we'll fetch fresh data
            return;
        }

        // Get a valid access token for the user (refreshes if necessary)
        $accessToken = $this->getValidAccessToken($user);
        if (!$accessToken) {
            log_message('error', "Cannot process new activity {$stravaActivityId}: Failed to get valid access token for User ID {$user['id']}.");
            return; // Stop if no valid token
        }

        // Fetch the full details of the activity from the Strava API
        $activityData = $this->fetchStravaActivityDetails($accessToken, $stravaActivityId);
        if (!$activityData) {
            log_message('error', "Cannot process new activity {$stravaActivityId}: Failed to fetch activity details from Strava API for User ID {$user['id']}.");
            return; // Stop if API call failed
        }

        // Prepare data for insertion into your local activities table
        // Map fields from the Strava API response ($activityData) to your DB columns
        $localActivityData = [
            'user_id' => $user['id'], // Your internal user ID from UserModel
            'strava_activity_id' => $stravaActivityId,
            'strava_athlete_id' => $user['strava_id'], // Store the Strava Athlete ID for reference
            'name' => $activityData['name'] ?? 'N/A',
            'distance' => $activityData['distance'] ?? 0.0,
            'moving_time' => $activityData['moving_time'] ?? 0,
            'elapsed_time' => $activityData['elapsed_time'] ?? 0,
            'type' => $activityData['type'] ?? 'Unknown',
            'sport_type' => $activityData['sport_type'] ?? 'Unknown', // Added sport_type
            'start_date' => $activityData['start_date'] ?? null, // UTC time Z format e.g., "2018-05-02T12:15:09Z"
            'start_date_local' => $activityData['start_date_local'] ?? null, // Local time format e.g., "2018-05-02T05:15:09Z"
            'timezone' => $activityData['timezone'] ?? null, // e.g., "(GMT-08:00) America/Los_Angeles"
            'total_elevation_gain' => $activityData['total_elevation_gain'] ?? 0.0,
            'average_speed' => $activityData['average_speed'] ?? 0.0,
            'max_speed' => $activityData['max_speed'] ?? 0.0,
            'has_heartrate' => $activityData['has_heartrate'] ?? false,
            'average_heartrate' => $activityData['average_heartrate'] ?? null,
            'max_heartrate' => $activityData['max_heartrate'] ?? null,
            'kudos_count' => $activityData['kudos_count'] ?? 0,
            'comment_count' => $activityData['comment_count'] ?? 0,
            'photo_count' => $activityData['total_photo_count'] ?? 0, // Use total_photo_count
            'map_polyline' => $activityData['map']['summary_polyline'] ?? null, // Store summary polyline
            'visibility' => $activityData['visibility'] ?? 'everyone', // Store visibility setting
            'gear_id' => $activityData['gear_id'] ?? null, // Store gear used
            // Add other fields as needed based on your ActivityModel schema
            // 'raw_data' => json_encode($activityData) // Optional: store the full response for debugging
        ];

        // Insert the data into your local database using ActivityModel
        try {
            // Ensure your ActivityModel allows these fields and handles types correctly
            if ($this->activityModel->insert($localActivityData)) {
                 log_message('info', "Successfully INSERTED new activity {$stravaActivityId} into local DB for User ID {$user['id']}");
            } else {
                 // Log validation errors if insertion fails
                 log_message('error', "Failed to INSERT new activity {$stravaActivityId} into local DB for User ID {$user['id']}. Validation Errors: " . json_encode($this->activityModel->errors()));
            }
        } catch (\Exception $e) {
            // Catch potential database exceptions (constraints, connection issues)
            log_message('error', "Database exception inserting activity {$stravaActivityId} for User ID {$user['id']}: " . $e->getMessage());
        }
    }

    /**
     * Process an activity update: Fetch details and UPDATE local ActivityModel record.
     * @param array $user User data from your UserModel
     * @param int $stravaActivityId The ID of the updated Strava activity
     * @param object|null $updates Object potentially containing changed fields (often limited info)
     */
    private function processUpdatedActivity(array $user, int $stravaActivityId, ?object $updates) {
        log_message('info', "Processing Strava activity UPDATE for User ID: {$user['id']}, Strava Activity ID: {$stravaActivityId}");
        // Log the limited update info received (if any)
        if ($updates) {
            log_message('debug', 'Updates payload received: ' . json_encode($updates));
            // Note: 'updates' often only contains limited info like 'title' or 'type', 'private'.
            // It's usually best to re-fetch the full activity for consistency.
        }

        // Get a valid access token
        $accessToken = $this->getValidAccessToken($user);
        if (!$accessToken) {
             log_message('error', "Cannot process update for activity {$stravaActivityId}: Failed to get valid access token for User ID {$user['id']}.");
             return;
        }

        // Always re-fetch the full activity data from Strava API for updates
        $activityData = $this->fetchStravaActivityDetails($accessToken, $stravaActivityId);
        if (!$activityData) {
             log_message('warning', "Could not fetch details for updated activity {$stravaActivityId} (User ID {$user['id']}). Maybe deleted? Cannot update local DB.");
             // If fetch fails (e.g., 404 Not Found), the activity might have been deleted between the webhook event and the fetch.
             // We probably don't need to do anything further here unless we want to explicitly check/delete the local record.
             return;
        }

        // Find the existing activity record in your local database
        $existingActivity = $this->activityModel
                                ->where('strava_activity_id', $stravaActivityId)
                                ->where('user_id', $user['id']) // Ensure it's for the correct user
                                ->first();

        if ($existingActivity) {
             // Prepare data for local database update based on the fresh data fetched
            $localActivityUpdateData = [
                // Map fields similar to processNewActivity, using fetched data
                'name' => $activityData['name'] ?? $existingActivity['name'], // Fallback to existing if fetch misses a field
                'distance' => $activityData['distance'] ?? $existingActivity['distance'],
                'moving_time' => $activityData['moving_time'] ?? $existingActivity['moving_time'],
                'elapsed_time' => $activityData['elapsed_time'] ?? $existingActivity['elapsed_time'],
                'type' => $activityData['type'] ?? $existingActivity['type'],
                'sport_type' => $activityData['sport_type'] ?? $existingActivity['sport_type'],
                'start_date' => $activityData['start_date'] ?? $existingActivity['start_date'],
                'start_date_local' => $activityData['start_date_local'] ?? $existingActivity['start_date_local'],
                'timezone' => $activityData['timezone'] ?? $existingActivity['timezone'],
                'total_elevation_gain' => $activityData['total_elevation_gain'] ?? $existingActivity['total_elevation_gain'],
                'average_speed' => $activityData['average_speed'] ?? $existingActivity['average_speed'],
                'max_speed' => $activityData['max_speed'] ?? $existingActivity['max_speed'],
                'has_heartrate' => $activityData['has_heartrate'] ?? $existingActivity['has_heartrate'],
                'average_heartrate' => $activityData['average_heartrate'] ?? $existingActivity['average_heartrate'],
                'max_heartrate' => $activityData['max_heartrate'] ?? $existingActivity['max_heartrate'],
                'kudos_count' => $activityData['kudos_count'] ?? $existingActivity['kudos_count'],
                'comment_count' => $activityData['comment_count'] ?? $existingActivity['comment_count'],
                'photo_count' => $activityData['total_photo_count'] ?? $existingActivity['photo_count'],
                'map_polyline' => $activityData['map']['summary_polyline'] ?? $existingActivity['map_polyline'],
                'visibility' => $activityData['visibility'] ?? $existingActivity['visibility'],
                'gear_id' => $activityData['gear_id'] ?? $existingActivity['gear_id'],
                // Add other fields...
                // 'raw_data' => json_encode($activityData), // Update raw data if stored
                 // Let CodeIgniter handle updated_at if model is configured with useTimestamps=true
                 // otherwise: 'updated_at' => date('Y-m-d H:i:s')
            ];

            // Update the record in the database using the primary key ('id') of the local record
            try {
                if ($this->activityModel->update($existingActivity['id'], $localActivityUpdateData)) {
                     log_message('info', "Successfully UPDATED activity {$stravaActivityId} in local DB for User ID {$user['id']}");
                } else {
                     log_message('error', "Failed to UPDATE activity {$stravaActivityId} in local DB for User ID {$user['id']}. Validation Errors: " . json_encode($this->activityModel->errors()));
                }
            } catch (\Exception $e) {
                log_message('error', "Database exception updating activity {$stravaActivityId} for User ID {$user['id']}: " . $e->getMessage());
            }
        } else {
            // If the activity wasn't found locally, it might be an edge case (e.g., webhook arrived before initial sync).
            // Treat it as a 'create' event to ensure the data gets into your system.
            log_message('warning', "Received update for activity {$stravaActivityId} which was not found in local DB for User ID {$user['id']}. Treating as new activity creation.");
            $this->processNewActivity($user, $stravaActivityId);
        }
    }

    /**
     * Process an activity deletion: DELETE record from local ActivityModel.
     * @param array $user User data from your UserModel
     * @param int $stravaActivityId The ID of the deleted Strava activity
     */
    private function processDeletedActivity(array $user, int $stravaActivityId) {
        log_message('info', "Processing Strava activity DELETE for User ID: {$user['id']}, Strava Activity ID: {$stravaActivityId}");

        try {
            // Delete the activity record from your local database matching the Strava ID and User ID
            // Using where clauses is safer than assuming a primary key
            $deleted = $this->activityModel->where('strava_activity_id', $stravaActivityId)
                                           ->where('user_id', $user['id']) // Ensure correct user
                                           ->delete();

            if ($deleted) {
                // Check if any rows were actually affected (delete returns true even if 0 rows matched)
                // $affectedRows = $this->activityModel->db->affectedRows(); // Get affected rows if needed
                // log_message('info', "Successfully DELETED activity {$stravaActivityId} for User ID {$user['id']} from local DB. Rows affected: " . $affectedRows);
                 log_message('info', "Successfully processed DELETE for activity {$stravaActivityId}, User ID {$user['id']} from local DB.");

            } else {
                // This often just means the activity was already deleted or never existed locally.
                log_message('warning', "Attempted to delete activity {$stravaActivityId} for User ID {$user['id']}, but it was not found in local DB (or already deleted). No action taken.");
            }
        } catch (\Exception $e) {
            // Catch potential database errors during deletion
            log_message('error', "Database exception deleting activity {$stravaActivityId} for User ID {$user['id']}: " . $e->getMessage());
        }
    }

     /**
      * Process athlete deauthorization: UPDATE user in UserModel to revoke access.
      * @param array $user User data from your UserModel
      */
    private function processDeauthorization(array $user) {
        log_message('warning', "Processing deauthorization for User ID: {$user['id']}, Strava Athlete ID: {$user['strava_id']}");

        // Update the user's record in your database to reflect the deauthorization
        // Crucially, clear the tokens and set a flag.
        try {
            // Ensure 'is_strava_authorized' field exists in your users table/UserModel
            $updateData = [
                'access_token' => null, // Clear the access token
                'refresh_token' => null, // Clear the refresh token
                'expires_at' => null, // Clear expiry
                'strava_scopes' => null, // Clear stored scopes
                'is_strava_authorized' => false // Set authorization flag to false
                // Add any other fields related to authorization status
            ];
            if ($this->userModel->update($user['id'], $updateData)) {
                 log_message('info', "Successfully processed deauthorization for User ID: {$user['id']} in local DB.");
            } else {
                 log_message('error', "Failed to update user record during deauthorization for User ID {$user['id']}. Errors: " . json_encode($this->userModel->errors()));
            }
        } catch (\Exception $e) {
            log_message('error', "Database exception processing deauthorization for User ID {$user['id']}: " . $e->getMessage());
        }
    }


    // ========================================================================
    // Helper Functions (Token Refresh & API Call)
    // ========================================================================

    /**
     * Checks if the access token is expired and refreshes it using the refresh token if necessary.
     * Updates the user record in the database with new tokens upon successful refresh.
     * Handles potential deauthorization if refresh fails.
     *
     * @param array &$user The user data array (passed by reference to update in memory). Must include 'id', 'strava_id', 'expires_at', 'refresh_token', 'access_token'.
     * @return string|null The valid access token (current or refreshed), or null on failure.
     */
    private function getValidAccessToken(array &$user): ?string
    {
        // Check if essential keys exist
        if (!isset($user['id'], $user['strava_id'], $user['expires_at'], $user['refresh_token'], $user['access_token'])) {
             log_message('error', "User array missing required keys for token refresh. User ID: " . ($user['id'] ?? 'N/A'));
             return null;
        }

        $currentTime = time();
        // Check if token expires within the next 60 seconds (buffer)
        if ($user['expires_at'] > ($currentTime + 60)) {
            log_message('debug', "Access token for User ID {$user['id']} is still valid.");
            return $user['access_token']; // Token is valid, return it
        }

        // Token expired or expiring soon, attempt refresh
        log_message('info', "Access token expired or expiring soon for User ID {$user['id']} (Strava ID {$user['strava_id']}). Refreshing...");

        // Load Strava credentials from .env
        $clientId = getenv('STRAVA_CLIENT_ID');
        $clientSecret = getenv('STRAVA_CLIENT_SECRET');
        if (!$clientId || !$clientSecret) {
            log_message('critical', 'Strava Client ID or Secret not configured in .env. Cannot refresh token.');
            return null;
        }

        // Use CodeIgniter's HTTP Client Service
        $client = Services::curlrequest([
            'base_uri' => 'https://www.strava.com/oauth/',
            'timeout' => 10, // Set a reasonable timeout for the token request
        ]);

        try {
            // Make the POST request to Strava's token endpoint
            $response = $client->post('token', [
                'form_params' => [
                    'client_id'     => $clientId,
                    'client_secret' => $clientSecret,
                    'grant_type'    => 'refresh_token',
                    'refresh_token' => $user['refresh_token'], // Use the user's refresh token
                ],
                'http_errors' => false // Prevent exceptions on 4xx/5xx responses, handle manually
            ]);

            $body = $response->getBody();
            $statusCode = $response->getStatusCode();
            $data = json_decode($body, true); // Decode JSON response into an array

            // Check for successful response (HTTP 200) and presence of access token
            if ($statusCode === 200 && isset($data['access_token'])) {
                log_message('info', "Token refreshed successfully for User ID {$user['id']}.");

                // Prepare data to update the user record in the database
                $updateData = [
                    'access_token'  => $data['access_token'],
                    'refresh_token' => $data['refresh_token'], // Strava *might* return a new refresh token, always update it
                    'expires_at'    => $data['expires_at'],   // Update the expiry time
                    'is_strava_authorized' => true // Ensure user is marked as authorized
                ];

                // Update the user record in the database
                if ($this->userModel->update($user['id'], $updateData)) {
                    log_message('debug', "Successfully updated tokens in DB for User ID {$user['id']}");
                } else {
                     log_message('error', "Failed to update tokens in DB for User ID {$user['id']}. Errors: " . json_encode($this->userModel->errors()));
                     // Continue, but token in DB might be stale if update failed
                }


                // Update the user array passed by reference so the calling function uses the new token immediately
                $user = array_merge($user, $updateData);

                return $data['access_token']; // Return the NEWLY obtained access token

            } else {
                // Handle token refresh failure
                log_message('error', "Failed to refresh token for User ID {$user['id']}. Status: {$statusCode}, Response: {$body}");

                // If refresh fails due to invalid grant (bad refresh token), user likely deauthorized app manually.
                if ($statusCode === 400 || $statusCode === 401 || (isset($data['error']) && $data['error'] === 'invalid_grant') || strpos($body, 'invalid_grant') !== false) {
                     log_message('warning', "Refresh token is likely invalid for User ID {$user['id']}. Marking user as deauthorized.");
                     // Trigger the deauthorization process to clean up locally
                     $this->processDeauthorization($user);
                }
                // Other errors might be temporary Strava issues.
                return null; // Refresh failed
            }
        } catch (\Exception $e) {
            // Catch exceptions during the HTTP request itself (e.g., connection timeout)
            log_message('error', "Exception during token refresh HTTP request for User ID {$user['id']}: " . $e->getMessage());
            return null; // Refresh failed due to exception
        }
    }

    /**
     * Fetches full activity details from the Strava API v3.
     *
     * @param string $accessToken A valid Strava access token.
     * @param int $stravaActivityId The ID of the activity to fetch.
     * @return array|null The decoded activity data as an associative array, or null on failure.
     */
    private function fetchStravaActivityDetails(string $accessToken, int $stravaActivityId): ?array
    {
        log_message('debug', "Fetching Strava activity details from API for Activity ID: {$stravaActivityId}");

        // Use CodeIgniter's HTTP Client Service
        $client = Services::curlrequest([
             'base_uri' => 'https://www.strava.com/api/v3/', // Base URL for Strava API v3
             'timeout' => 15, // Allow slightly longer timeout for API calls vs token calls
        ]);

        try {
            // Make the GET request to the activities endpoint
            $response = $client->get("activities/{$stravaActivityId}", [
                'headers' => [
                    'Authorization' => 'Bearer ' . $accessToken, // Pass the access token in the header
                    'Accept'        => 'application/json',     // Specify we want JSON response
                ],
                'http_errors' => false // Handle HTTP errors manually
            ]);

            $body = $response->getBody();
            $statusCode = $response->getStatusCode();
            $data = json_decode($body, true); // Decode JSON response to associative array

            // Check for successful API response (HTTP 200)
            if ($statusCode === 200) {
                log_message('info', "Successfully fetched activity details from API for ID: {$stravaActivityId}");
                return $data; // Return the decoded activity data
            } else {
                // Handle API errors
                log_message('error', "Failed to fetch activity details for ID {$stravaActivityId}. API Status: {$statusCode}, Response: {$body}");

                // Specific error handling can be added here:
                 if ($statusCode === 404) {
                     log_message('warning', "Activity {$stravaActivityId} not found on Strava (API returned 404). It might be deleted or private.");
                 } else if ($statusCode === 401 || $statusCode === 403) {
                     // This could indicate the token is invalid/expired (though refresh should handle expiry)
                     // or doesn't have the required scope (activity:read_all).
                     log_message('error', "Unauthorized (401/403) fetching activity {$stravaActivityId}. Token might be invalid or lack scope.");
                     // Consider triggering token refresh or deauthorization if this persists
                 } else if ($statusCode >= 500) {
                      log_message('error', "Strava API server error ({$statusCode}) fetching activity {$stravaActivityId}.");
                      // Could be a temporary issue on Strava's end.
                 }
                return null; // Return null on failure
            }
        } catch (\Exception $e) {
            // Catch exceptions during the HTTP request (e.g., connection issues)
            log_message('error', "Exception during Strava API request for activity ID {$stravaActivityId}: " . $e->getMessage());
            return null; // Return null on exception
        }
    }
}
