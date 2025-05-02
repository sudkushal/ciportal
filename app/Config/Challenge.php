<?php

namespace Config;

use CodeIgniter\Config\BaseConfig;
use CodeIgniter\I18n\Time; // Use Time class for dates

class Challenge extends BaseConfig
{
    /**
     * Main Challenge Settings
     * Note: Dates are for testing as requested. Adjust for production.
     */
    public string $challengeName = '100 Day Challenge Test';
    public string $startDate = '2024-08-15'; // YYYY-MM-DD
    public int $totalDurationDays = 100;

    /**
     * Scoring Rules for Activities (used if points are calculated per activity)
     * Points per kilometer.
     */
    public array $activityPointsPerKm = [
        'Walk' => 5.0,
        'Run'  => 5.0,
        'Ride' => 1.25, // 5 points / 4km ratio
        // Add other types if needed, e.g. 'Hike' => 5.0
    ];

    /**
     * Stage Definitions
     * Defines the duration and calculates start/end dates for each stage.
     * Also holds the specific sub-challenge configurations for that stage.
     */
    public array $stages = [];

    // Constructor to calculate dates and populate stages dynamically
    public function __construct()
    {
        parent::__construct(); // Call parent constructor if needed

        $challengeStartDate = Time::parse($this->startDate, 'UTC'); // Treat start date as UTC
        $currentStageStartDate = $challengeStartDate;
        $stageDuration = 20; // Duration of each stage

        for ($i = 1; $i <= 5; $i++) {
            $stageStartDate = $currentStageStartDate;
            // Calculate end date (start date + duration - 1 day)
            $stageEndDate = $currentStageStartDate->addDays($stageDuration - 1);

            $this->stages[$i] = [
                'stage_number' => $i,
                'name' => 'Stage ' . $i,
                'duration_days' => $stageDuration,
                'start_date' => $stageStartDate->toDateString(), // YYYY-MM-DD
                'end_date' => $stageEndDate->toDateString(),     // YYYY-MM-DD
                'sub_challenges' => $this->getSubChallengeConfigForStage($i) // Get specific rules
            ];

            // Set the start date for the next stage
            $currentStageStartDate = $stageEndDate->addDays(1);
        }
    }

    /**
     * Defines the sub-challenge rules for a given stage number.
     * This is where you define min/max days, distances, points etc. per stage.
     *
     * @param int $stageNumber
     * @return array
     */
    private function getSubChallengeConfigForStage(int $stageNumber): array
    {
        // --- Configuration based on user input for Stage 1 (used as default) ---
        $config = [
            'daily' => [
                'challenge_type' => 'daily',
                'activity_types' => ['Walk', 'Run', 'Ride'], // Activities that count
                'min_days' => 12, // Minimum days activity needed in the stage
                'max_days' => 16, // Max days that count towards this (optional usage)
                'min_distance_meters_walk_run' => 5000, // Min distance PER activity to count for a day?
                'min_distance_meters_ride' => 20000,    // Min distance PER activity to count for a day?
                'points_awarded' => 100, // Points for completing the daily challenge (e.g., meeting min_days)
                'description' => 'Min 12 / Max 16 days activity (>5km Walk/Run OR >20km Ride)',
            ],
            'advanced' => [
                'challenge_type' => 'advanced',
                'activity_types' => ['Walk', 'Run', 'Ride'],
                'min_days' => 6,
                'max_days' => 8,
                'min_distance_meters_walk_run' => 6000, // Min distance PER qualifying activity
                'min_distance_meters_ride' => 24000,    // Min distance PER qualifying activity
                'points_awarded' => 200, // Points for completing the advanced challenge
                'description' => 'Min 6 / Max 8 days advanced activity (>6km Walk/Run OR >24km Ride)',
            ],
            'extreme' => [
                'challenge_type' => 'extreme',
                'activity_types' => ['Walk', 'Run', 'Ride'],
                'min_days' => 1, // Must complete 1 extreme activity
                'max_days' => 1,
                'min_distance_meters_walk_run' => 7500, // Min distance for the single extreme activity
                'min_distance_meters_ride' => 30000,    // Min distance for the single extreme activity
                'points_awarded' => 500, // Points awarded for completing ONE extreme activity
                'description' => '1 day extreme activity (>7.5km Walk/Run OR >30km Ride)',
            ],
            'distance' => [
                'challenge_type' => 'distance',
                'activity_types' => ['Walk', 'Run', 'Ride'], // Types to sum distance for
                // --- KEEPING DISTANCE CONFIG OPEN AS REQUESTED ---
                'min_distance_meters_walk_run' => 20000, // Set this value later (Min TOTAL distance for stage)
                'min_distance_meters_ride' => 80000,    // Set this value later (Min TOTAL distance for stage)
                'points_awarded' => 1000, // Set points later
                'description' => 'Minimum total distance challenge',
            ],
        ];

        // --- Stage-Specific Adjustments (Example - You can modify these later) ---
        // This allows you to override the default config for specific stages
        switch ($stageNumber) {
            // case 1: // No changes needed as default matches stage 1 input
            //     break;
            case 2:
                $config['daily']['points_awarded'] = 110;
                $config['daily']['min_distance_meters_walk_run'] = 6000;
                $config['daily']['min_distance_meters_ride'] = 24000;
                $config['advanced']['min_distance_meters_walk_run'] = 6500;
                $config['advanced']['min_distance_meters_ride'] = 26000;
                $config['advanced']['points_awarded'] = 220;
                $config['extreme']['min_distance_meters_walk_run'] = 8000;
                $config['extreme']['min_distance_meters_ride'] = 32000;
                $config['extreme']['points_awarded'] = 420;
                $config['distance']['min_distance_meters_walk_run'] = 25000;
                $config['distance']['min_distance_meters_ride'] = 100000;
                $config['distance']['points_awarded'] = 1200;
                break;
            case 3:
                // ... adjust rules for stage 3 ...
                break;
            case 4:
                // ... adjust rules for stage 4 ...
                break;
            case 5:
                // ... adjust rules for stage 5 ...
                 $config['extreme']['min_distance_meters_walk_run'] = 20000; // Example higher target
                 $config['extreme']['min_distance_meters_ride'] = 80000;   // Example higher target
                 $config['extreme']['points_awarded'] = 600;               // Example higher points
                break;
        }

        return $config;
    }

    /**
     * Helper function to get the calculated end date of the challenge
     * @return string YYYY-MM-DD format
     */
    public function getChallengeEndDate(): string
    {
        return Time::parse($this->startDate, 'UTC')->addDays($this->totalDurationDays - 1)->toDateString();
    }
}
