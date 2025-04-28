<?php

namespace App\Models;

use CodeIgniter\Model;

class ActivityModel extends Model
{
    protected $table            = 'activities'; // Set your activities table name
    protected $primaryKey       = 'id';       // Your table's primary key

    protected $useAutoIncrement = true;

    protected $returnType       = 'array';    // Or 'object' or your custom class
    protected $useSoftDeletes   = false;      // Set to true if you added a 'deleted_at' column

    // Define the columns that are allowed to be saved/updated via the model
    protected $allowedFields    = [
        'user_id',
        'strava_activity_id',
        'strava_athlete_id',
        'name',
        'distance',
        'moving_time',
        'elapsed_time',
        'type',
        'sport_type',
        'start_date',
        'start_date_local',
        'timezone',
        'total_elevation_gain',
        'average_speed',
        'max_speed',
        'has_heartrate',
        'average_heartrate',
        'max_heartrate',
        'kudos_count',
        'comment_count',
        'photo_count',
        'map_polyline',
        'visibility',
        'gear_id',
        // Add any other fields you have in your 'activities' table
        // 'raw_data' // If you decided to store the raw JSON
    ];

    // Dates - Enable if you want CodeIgniter to handle created_at/updated_at
    protected $useTimestamps = true; // Set true to automatically handle created_at, updated_at
    protected $dateFormat    = 'datetime'; // 'datetime', 'date', or 'int'
    protected $createdField  = 'created_at';
    protected $updatedField  = 'updated_at';
    // protected $deletedField  = 'deleted_at'; // Uncomment if useSoftDeletes is true

    // Validation (Optional but Recommended)
    // protected $validationRules      = [];
    // protected $validationMessages   = [];
    // protected $skipValidation       = false;
    // protected $cleanValidationRules = true;

    // Callbacks (Optional)
    // protected $allowCallbacks = true;
    // protected $beforeInsert   = [];
    // protected $afterInsert    = [];
    // protected $beforeUpdate   = [];
    // protected $afterUpdate    = [];
    // protected $beforeFind     = [];
    // protected $afterFind      = [];
    // protected $beforeDelete   = [];
    // protected $afterDelete    = [];
}