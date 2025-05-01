<?php

namespace App\Models;

use CodeIgniter\Model;
use CodeIgniter\I18n\Time; // Import Time for date handling

class ActivityModel extends Model
{
    protected $table            = 'activities'; // Table name defined above
    protected $primaryKey       = 'id';         // Your app's primary key for activities

    protected $useAutoIncrement = true;

    // Specify the return type for find* methods
    protected $returnType       = 'object';

    // Define fields that are allowed to be saved/updated via the model
    protected $allowedFields    = [
        'user_id',
        'strava_activity_id',
        'strava_athlete_id',
        'name',
        'type',
        'start_date',
        'start_date_local',
        'timezone',
        'distance',
        'moving_time',
        'elapsed_time',
        'total_elevation_gain',
        'average_speed',
        'max_speed',
        'kudos_count',
        'gear_id',
        'external_id',
        'upload_id',
        // Add other fields from Strava API as needed
    ];

    // Enable automatic handling of created_at, updated_at timestamps
    protected $useTimestamps = true;
    protected $dateFormat    = 'datetime';
    protected $createdField  = 'created_at';
    protected $updatedField  = 'updated_at';
    // protected $deletedField  = 'deleted_at'; // Uncomment if using soft deletes

    /**
     * Saves multiple Strava activities for a given user.
     * It checks if an activity already exists based on strava_activity_id
     * and updates it, otherwise inserts a new record.
     *
     * @param int   $userId         Your application's user ID.
     * @param array $activitiesData An array of activity objects from the Strava API.
     * @return array Counts of inserted and updated records: ['inserted' => int, 'updated' => int, 'failed' => int]
     */
    public function syncActivities(int $userId, array $activitiesData): array
    {
        $results = ['inserted' => 0, 'updated' => 0, 'failed' => 0];

        if (empty($activitiesData)) {
            return $results;
        }

        foreach ($activitiesData as $activity) {
            // Basic validation: Ensure essential fields exist
            if (!isset($activity->id) || !isset($activity->athlete->id)) {
                 log_message('warning', 'Skipping activity sync due to missing ID. Data: ' . json_encode($activity));
                 $results['failed']++;
                 continue;
            }

            // Prepare data for database insertion/update
            $data = [
                'user_id'              => $userId,
                'strava_activity_id'   => $activity->id,
                'strava_athlete_id'    => $activity->athlete->id,
                'name'                 => $activity->name ?? 'Untitled Activity',
                'type'                 => $activity->type ?? 'Unknown',
                'start_date'           => Time::parse($activity->start_date)->setTimezone('UTC')->toDateTimeString(), // Store as UTC
                'start_date_local'     => Time::parse($activity->start_date_local)->toDateTimeString(), // Store as local
                'timezone'             => $activity->timezone ?? '',
                'distance'             => $activity->distance ?? null,
                'moving_time'          => $activity->moving_time ?? null,
                'elapsed_time'         => $activity->elapsed_time ?? null,
                'total_elevation_gain' => $activity->total_elevation_gain ?? null,
                'average_speed'        => $activity->average_speed ?? null,
                'max_speed'            => $activity->max_speed ?? null,
                'kudos_count'          => $activity->kudos_count ?? 0,
                'gear_id'              => $activity->gear_id ?? null,
                'external_id'          => $activity->external_id ?? null,
                'upload_id'            => $activity->upload_id ?? null,
            ];

            // Check if activity already exists
            $existingActivity = $this->where('strava_activity_id', $activity->id)
                                     ->select('id') // Only need the ID
                                     ->first();

            try {
                if ($existingActivity) {
                    // Update existing activity
                    if ($this->update($existingActivity->id, $data)) {
                        $results['updated']++;
                    } else {
                         log_message('error', 'Failed to update activity ID ' . $activity->id . '. Errors: ' . json_encode($this->errors()));
                         $results['failed']++;
                    }
                } else {
                    // Insert new activity
                    if ($this->insert($data)) {
                        $results['inserted']++;
                    } else {
                        log_message('error', 'Failed to insert activity ID ' . $activity->id . '. Errors: ' . json_encode($this->errors()));
                        $results['failed']++;
                    }
                }
            } catch (\Exception $e) {
                 log_message('error', 'Database exception syncing activity ID ' . $activity->id . ': ' . $e->getMessage());
                 $results['failed']++;
            }
        } // end foreach

        log_message('info', "Activity sync results for user ID {$userId}: " . json_encode($results));
        return $results;
    }
}
