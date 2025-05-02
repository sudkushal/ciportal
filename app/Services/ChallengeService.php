<?php

namespace App\Services; // Or place in a Controller or Model

use Config\Challenge as ChallengeConfig; // Use alias for clarity
use App\Models\ActivityModel;
use CodeIgniter\I18n\Time;
use RuntimeException; // To throw specific exceptions

class ChallengeService
{
    protected $challengeConfig;
    protected $activityModel;
    protected $activityPointsMap = []; // Keep this for activity type filtering if needed

    public function __construct()
    {
        $config = config('Challenge'); // Load the challenge config

        // --- CONFIG VALIDATION ---
        if (!$config instanceof ChallengeConfig) {
            log_message('error', 'Failed to load Challenge configuration.');
            throw new RuntimeException('Failed to load Challenge configuration.');
        }
        $this->challengeConfig = $config;
        if (!property_exists($this->challengeConfig, 'activityPointsPerKm') || !is_array($this->challengeConfig->activityPointsPerKm)) {
             log_message('error', 'Challenge configuration is missing or has invalid activityPointsPerKm property.');
             throw new RuntimeException('Challenge configuration is missing or has invalid activityPointsPerKm property.');
        }
         if (!method_exists($this->challengeConfig, 'getChallengeEndDate')) {
             log_message('error', 'Challenge configuration is missing getChallengeEndDate method.');
             throw new RuntimeException('Challenge configuration is missing getChallengeEndDate method.');
        }
        // --- END CONFIG VALIDATION ---

        $this->activityPointsMap = $this->challengeConfig->activityPointsPerKm;
        $this->activityModel = new ActivityModel();
    }

    /**
     * Calculates the total points and breakdown for a specific user based on fixed points per challenge.
     *
     * @param int $userId The user's ID in your application.
     * @return array An array containing total points and potentially stage/challenge breakdown.
     */
    public function calculateUserScore(int $userId): array
    {
        $totalPoints = 0;
        $pointsBreakdown = []; // Stores points awarded per stage/challenge type

        // 1. Fetch all relevant activities for the user within the challenge duration
        $challengeStartDate = $this->challengeConfig->startDate;
        $challengeEndDate = $this->challengeConfig->getChallengeEndDate();
        $relevantTypes = array_keys($this->activityPointsMap);
        if (empty($relevantTypes)) {
             log_message('warning', 'No activity types found in challenge config.');
             return ['total_points' => 0, 'breakdown' => []];
        }

        $userActivities = $this->activityModel
            ->where('user_id', $userId)
            ->whereIn('type', $relevantTypes)
            ->where('start_date >=', $challengeStartDate . ' 00:00:00')
            ->where('start_date <=', $challengeEndDate . ' 23:59:59')
            ->orderBy('start_date', 'ASC')
            ->findAll();

        // No need to proceed if user has no activities in the challenge period
        // Note: We still need to loop stages if a challenge gives points for 0 activity (unlikely)
        // if (empty($userActivities)) {
        //     return ['total_points' => 0, 'breakdown' => []];
        // }

        // 2. Iterate through each stage defined in the config
        if (!isset($this->challengeConfig->stages) || !is_array($this->challengeConfig->stages)) {
             log_message('error', 'Challenge configuration stages are missing or invalid.');
             throw new RuntimeException('Challenge configuration stages are missing or invalid.');
        }

        foreach ($this->challengeConfig->stages as $stageNumber => $stageConfig) {
            if (!isset($stageConfig['start_date']) || !isset($stageConfig['end_date']) || !isset($stageConfig['sub_challenges'])) {
                 log_message('error', "Invalid configuration structure for Stage {$stageNumber}. Skipping.");
                 continue;
            }

            $stageStartDate = $stageConfig['start_date'];
            $stageEndDate = $stageConfig['end_date'];
            $stageSubChallenges = $stageConfig['sub_challenges'];
            $stagePoints = 0; // Points accumulated in this stage
            $pointsBreakdown[$stageNumber] = []; // Initialize breakdown for the stage

            // 3. Filter activities for the current stage
            $stageActivities = array_filter($userActivities, function ($activity) use ($stageStartDate, $stageEndDate) {
                 if (!isset($activity->start_date) || empty($activity->start_date)) return false;
                $activityDate = substr($activity->start_date, 0, 10);
                return $activityDate >= $stageStartDate && $activityDate <= $stageEndDate;
            });

             log_message('debug', "Stage {$stageNumber}: Evaluating with " . count($stageActivities) . " activities within date range {$stageStartDate} to {$stageEndDate}.");


            // --- Evaluate Sub-Challenges for this Stage (Award Fixed Points) ---
            if (!is_array($stageSubChallenges)) {
                 log_message('error', "Sub-challenges configuration is invalid for Stage {$stageNumber}. Skipping evaluation.");
                 continue;
            }

            // A. Daily Challenge Evaluation
            if (isset($stageSubChallenges['daily']) && is_array($stageSubChallenges['daily'])) {
                $dailyConfig = $stageSubChallenges['daily'];
                if (isset($dailyConfig['min_days'])) {
                    $dailyQualifiedDays = $this->countQualifiedDays($stageActivities, $dailyConfig);
                    if ($dailyQualifiedDays >= $dailyConfig['min_days']) {
                        $pointsToAdd = $dailyConfig['points_awarded'] ?? 0;
                        $stagePoints += $pointsToAdd;
                        $pointsBreakdown[$stageNumber]['daily'] = $pointsToAdd; // Store awarded points
                        log_message('debug',"Stage {$stageNumber} Daily: Met {$dailyQualifiedDays}/{$dailyConfig['min_days']} days. Awarded fixed points: {$pointsToAdd}");
                    } else {
                        $pointsBreakdown[$stageNumber]['daily'] = 0; // Explicitly set to 0 if not met
                        log_message('debug',"Stage {$stageNumber} Daily: Did not meet min days ({$dailyQualifiedDays}/{$dailyConfig['min_days']}). Points: 0");
                    }
                } else {
                     log_message('warning', "Stage {$stageNumber} Daily challenge config missing 'min_days'.");
                     $pointsBreakdown[$stageNumber]['daily'] = 0;
                }
            }

            // B. Advanced Challenge Evaluation
            if (isset($stageSubChallenges['advanced']) && is_array($stageSubChallenges['advanced'])) {
                $advancedConfig = $stageSubChallenges['advanced'];
                 if (isset($advancedConfig['min_days'])) {
                     $advancedQualifiedDays = $this->countQualifiedDays($stageActivities, $advancedConfig);
                     if ($advancedQualifiedDays >= $advancedConfig['min_days']) {
                         $pointsToAdd = $advancedConfig['points_awarded'] ?? 0;
                         $stagePoints += $pointsToAdd;
                         $pointsBreakdown[$stageNumber]['advanced'] = $pointsToAdd;
                         log_message('debug',"Stage {$stageNumber} Advanced: Met {$advancedQualifiedDays}/{$advancedConfig['min_days']} days. Awarded fixed points: {$pointsToAdd}");
                    } else {
                         $pointsBreakdown[$stageNumber]['advanced'] = 0;
                         log_message('debug',"Stage {$stageNumber} Advanced: Did not meet min days ({$advancedQualifiedDays}/{$advancedConfig['min_days']}). Points: 0");
                    }
                 } else {
                      log_message('warning', "Stage {$stageNumber} Advanced challenge config missing 'min_days'.");
                      $pointsBreakdown[$stageNumber]['advanced'] = 0;
                 }
            }

            // C. Extreme Challenge Evaluation
            if (isset($stageSubChallenges['extreme']) && is_array($stageSubChallenges['extreme'])) {
                $extremeConfig = $stageSubChallenges['extreme'];
                if ($this->checkExtremeChallenge($stageActivities, $extremeConfig)) {
                    $pointsToAdd = $extremeConfig['points_awarded'] ?? 0;
                    $stagePoints += $pointsToAdd;
                    $pointsBreakdown[$stageNumber]['extreme'] = $pointsToAdd;
                    log_message('debug',"Stage {$stageNumber} Extreme: Completed. Awarded fixed points: {$pointsToAdd}");
                } else {
                     $pointsBreakdown[$stageNumber]['extreme'] = 0;
                     log_message('debug',"Stage {$stageNumber} Extreme: Not completed. Points: 0");
                }
            }

            // D. Distance Challenge Evaluation
             if (isset($stageSubChallenges['distance']) && is_array($stageSubChallenges['distance'])) {
                $distanceConfig = $stageSubChallenges['distance'];
                if ($this->checkDistanceChallenge($stageActivities, $distanceConfig)) {
                     $pointsToAdd = $distanceConfig['points_awarded'] ?? 0;
                     $stagePoints += $pointsToAdd;
                     $pointsBreakdown[$stageNumber]['distance'] = $pointsToAdd;
                     log_message('debug',"Stage {$stageNumber} Distance: Completed. Awarded fixed points: {$pointsToAdd}");
                } else {
                     $pointsBreakdown[$stageNumber]['distance'] = 0;
                     log_message('debug',"Stage {$stageNumber} Distance: Not completed. Points: 0");
                }
             }


            $totalPoints += $stagePoints;
            $pointsBreakdown[$stageNumber]['total_stage_points'] = $stagePoints; // Store total for the stage

        } // End stage loop

        return [
            'total_points' => round($totalPoints, 2),
            'breakdown' => $pointsBreakdown, // Contains points per stage/challenge type
        ];
    }

    // --- Helper methods for evaluating challenges ---

    /**
     * Counts unique days within a stage where at least one activity met the criteria.
     * (Includes checks for property existence)
     */
    private function countQualifiedDays(array $stageActivities, array $config): int
    {
        $qualifiedDays = [];
        $activityTypes = $config['activity_types'] ?? [];

        foreach ($stageActivities as $activity) {
            if (!isset($activity->start_date) || empty($activity->start_date)) continue;
            $activityDate = substr($activity->start_date, 0, 10);
            if (isset($qualifiedDays[$activityDate])) continue;
            if (!isset($activity->type) || !in_array($activity->type, $activityTypes)) continue;

            $distanceMeters = $activity->distance ?? 0;
            $minDistance = 0; // Default: any activity of the right type counts for the day
            if (isset($config['min_distance_meters_walk_run']) || isset($config['min_distance_meters_ride'])) {
                // Only apply distance check if specified for the challenge type
                 if (in_array($activity->type, ['Walk', 'Run'])) {
                    $minDistance = $config['min_distance_meters_walk_run'] ?? 0;
                } elseif ($activity->type === 'Ride') {
                    $minDistance = $config['min_distance_meters_ride'] ?? 0;
                }
            }


            if ($distanceMeters >= $minDistance) {
                $qualifiedDays[$activityDate] = true;
            }
        }
        return count($qualifiedDays);
    }

     // Method sumPointsForQualifiedActivities is removed as it's no longer needed

    /**
     * Checks if at least one activity meets the extreme challenge criteria.
     * (Includes checks for property existence)
     */
    private function checkExtremeChallenge(array $stageActivities, array $config): bool
    {
        $activityTypes = $config['activity_types'] ?? [];

        foreach ($stageActivities as $activity) {
            if (!isset($activity->type) || !in_array($activity->type, $activityTypes)) continue;

            $distanceMeters = $activity->distance ?? 0;
            $minDistance = PHP_INT_MAX; // Set high default, require config value
            if (in_array($activity->type, ['Walk', 'Run'])) {
                $minDistance = $config['min_distance_meters_walk_run'] ?? PHP_INT_MAX;
            } elseif ($activity->type === 'Ride') {
                $minDistance = $config['min_distance_meters_ride'] ?? PHP_INT_MAX;
            }

            if ($minDistance !== PHP_INT_MAX && $distanceMeters >= $minDistance) { // Ensure minDistance was set
                return true;
            }
        }
        return false;
    }

    /**
     * Checks if total distance meets the distance challenge criteria.
     * (Includes checks for property existence)
     */
    private function checkDistanceChallenge(array $stageActivities, array $config): bool
    {
        $totalDistanceWalkRun = 0;
        $totalDistanceRide = 0;

        foreach ($stageActivities as $activity) {
             if (!isset($activity->type)) continue;
             if (in_array($activity->type, ['Walk', 'Run'])) {
                 $totalDistanceWalkRun += $activity->distance ?? 0;
             } elseif ($activity->type === 'Ride') {
                 $totalDistanceRide += $activity->distance ?? 0;
             }
        }

        // Check if goals are met. A null goal means it's not required for that type.
        $walkRunGoalMet = !isset($config['min_distance_meters_walk_run']) || $totalDistanceWalkRun >= $config['min_distance_meters_walk_run'];
        $rideGoalMet = !isset($config['min_distance_meters_ride']) || $totalDistanceRide >= $config['min_distance_meters_ride'];

        // Logic assumes BOTH criteria must be met if both are set AND non-null.
        // If only one distance goal is set, the other is ignored.
        if (!isset($config['min_distance_meters_walk_run']) && !isset($config['min_distance_meters_ride'])) {
            return false; // No distance goal defined for this challenge type
        }
        if (!isset($config['min_distance_meters_walk_run'])) return $rideGoalMet;
        if (!isset($config['min_distance_meters_ride'])) return $walkRunGoalMet;

        return $walkRunGoalMet && $rideGoalMet;
    }

} // End Class
