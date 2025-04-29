<?php

namespace App\Models;

use CodeIgniter\Model;

class StravaConfigModel extends Model
{
    // --- Configuration ---

    // !! IMPORTANT !!
    // Set the table name to match the one you created manually in your database.
    protected $table = 'strava_config';

    // Set the primary key field name.
    protected $primaryKey = 'id';

    // Define which fields in the table are allowed to be set
    // through methods like insert(), update(), save().
    // Add 'client_id', 'client_secret', 'config_name' if you ever plan
    // to update these values programmatically via the model.
    protected $allowedFields = ['client_id', 'client_secret', 'config_name'];

    // --- Timestamps ---

    // Enable automatic handling of 'created_at' and 'updated_at' columns.
    // Set this to false if your table doesn't have these columns.
    protected $useTimestamps = true;
    protected $dateFormat    = 'datetime'; // Or 'int' or 'date'
    protected $createdField  = 'created_at'; // Database column name for creation timestamp
    protected $updatedField  = 'updated_at'; // Database column name for update timestamp

    // --- Soft Deletes ---

    // Set to true if you have a 'deleted_at' column for soft deletes.
    protected $useSoftDeletes = false; // Assuming no soft deletes for this simple config table
    // protected $deletedField  = 'deleted_at'; // Column name for soft delete timestamp

    // --- Auto Increment ---

    // Usually true for primary keys.
    protected $useAutoIncrement = true;

    // --- Return Type ---

    // Define the format of results returned by find* methods.
    // 'array', 'object', or a custom class name.
    protected $returnType = 'object'; // 'object' is convenient for accessing properties like $config->client_id

    // --- Validation ---
    // Optional: Define validation rules if you build forms to edit these settings.
    // protected $validationRules = [
    //     'client_id' => 'required|max_length[100]',
    //     'client_secret' => 'required|max_length[100]',
    //     'config_name' => 'required|max_length[50]|is_unique[strava_config.config_name,id,{id}]' // Example
    // ];
    // protected $validationMessages = [];
    // protected $skipValidation = false;


    // --- Custom Method to Fetch Credentials ---

    /**
     * Fetches the Strava API credentials from the database.
     *
     * @param string $configName The name of the configuration entry to fetch (e.g., 'default').
     * @return object|null An object containing 'client_id' and 'client_secret', or null if not found.
     */
    public function getCredentials(string $configName = 'default'): ?object
    {
        // Find the configuration row where 'config_name' matches the provided name.
        $config = $this->where('config_name', $configName)
                       ->select('client_id, client_secret') // Only select the needed fields for efficiency.
                       ->first(); // Retrieve the first matching row.

        // Return the result (an object with properties, or null if no match).
        return $config;
    }
}
