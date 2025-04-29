<?php

namespace App\Models;

use CodeIgniter\Model;

class UserModel extends Model
{
    protected $table            = 'users'; // Table name defined above
    protected $primaryKey       = 'id';    // Your app's primary key

    protected $useAutoIncrement = true;

    // Specify the return type for find* methods
    protected $returnType       = 'object'; // Or 'array' or a custom User entity class

    // Define fields that are allowed to be saved/updated via the model
    protected $allowedFields    = [
        'strava_id',
        'firstname',
        'lastname',
        'profile_picture_url',
        'access_token',
        'refresh_token',
        'token_expires_at',
        'scope',
        'last_login',
    ];

    // Enable automatic handling of created_at, updated_at timestamps
    protected $useTimestamps = true;
    protected $dateFormat    = 'datetime';
    protected $createdField  = 'created_at';
    protected $updatedField  = 'updated_at';
    // protected $deletedField  = 'deleted_at'; // Uncomment if using soft deletes

    // Optional: Validation rules
    // protected $validationRules = [
    //     'strava_id' => 'required|is_natural_no_zero|is_unique[users.strava_id,id,{id}]',
    //     // Add other rules as needed
    // ];
    // protected $validationMessages = [];
    // protected $skipValidation = false;

    /**
     * Finds or creates a user based on Strava data.
     * Updates tokens and profile info if the user already exists.
     *
     * @param object $stravaAthlete The athlete data object from Strava API.
     * @param object $tokenData     The token data object (access_token, refresh_token, expires_at, scope).
     * @return int|string|false The ID of the created/updated user, or false on failure.
     */
    public function findOrCreate(object $stravaAthlete, object $tokenData)
    {
        // Prepare data for insertion or update
        $userData = [
            'strava_id'           => $stravaAthlete->id,
            'firstname'           => $stravaAthlete->firstname ?? null,
            'lastname'            => $stravaAthlete->lastname ?? null,
            'profile_picture_url' => $stravaAthlete->profile_medium ?? $stravaAthlete->profile ?? null, // Use medium or small profile pic
            'access_token'        => $tokenData->access_token,
            'refresh_token'       => $tokenData->refresh_token,
            'token_expires_at'    => $tokenData->expires_at,
            'scope'               => $tokenData->scope ?? null, // Assuming scope is part of tokenData
            'last_login'          => date('Y-m-d H:i:s'),
        ];

        // Check if user exists based on Strava ID
        $existingUser = $this->where('strava_id', $stravaAthlete->id)->first();

        try {
            if ($existingUser) {
                // User exists, update their info (tokens, name, picture, last login)
                log_message('info', 'Updating existing user: Strava ID ' . $stravaAthlete->id);
                if ($this->update($existingUser->id, $userData)) {
                    return $existingUser->id; // Return existing user's ID
                } else {
                    log_message('error', 'Failed to update user: Strava ID ' . $stravaAthlete->id . ' Errors: ' . json_encode($this->errors()));
                    return false; // Update failed
                }
            } else {
                // User doesn't exist, insert new record
                log_message('info', 'Creating new user: Strava ID ' . $stravaAthlete->id);
                // Use insert() which respects $allowedFields
                $newUserId = $this->insert($userData, true); // Set second param true to return ID
                if ($newUserId) {
                    return $newUserId; // Return new user's ID
                } else {
                     log_message('error', 'Failed to create new user: Strava ID ' . $stravaAthlete->id . ' Errors: ' . json_encode($this->errors()));
                    return false; // Insert failed
                }
            }
        } catch (\Exception $e) {
            log_message('error', 'Database error during findOrCreate for Strava ID ' . $stravaAthlete->id . ': ' . $e->getMessage());
            return false;
        }
    }
}
