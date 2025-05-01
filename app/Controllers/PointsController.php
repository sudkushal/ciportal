<?php

namespace App\Controllers;

use App\Models\UserModel;       // To get user names
use App\Models\ActivityModel;   // To get activities
use App\Controllers\BaseController;

class PointsController extends BaseController
{
    /**
     * Displays the points leaderboard.
     */
    public function index()
    {
        $userModel = new UserModel();
        $activityModel = new ActivityModel();

        // Define points per KM for each activity type
        $pointsPerKm = [
            'Walk' => 5.0,
            'Run'  => 5.0,
            'Ride' => 1.25, // 5 points per 4km -> 1.25 points per 1km
            // Add other relevant types if needed, e.g., Hike, Swim etc. with their points
        ];
        $relevantActivityTypes = array_keys($pointsPerKm); // ['Walk', 'Run', 'Ride']

        $userPoints = [];

        try {
            // Fetch all activities of relevant types
            // Note: For large datasets, fetching all activities might be slow.
            // Consider fetching aggregated data per user if performance becomes an issue.
            $allActivities = $activityModel
                ->whereIn('type', $relevantActivityTypes) // Only get Walk, Run, Ride
                ->select('user_id, type, distance') // Select only needed fields
                ->findAll(); // Fetch all matching activities

            if (!empty($allActivities)) {
                // Calculate points for each activity and aggregate per user
                foreach ($allActivities as $activity) {
                    // Ensure distance is not null and type is valid
                    if (isset($activity->distance) && isset($pointsPerKm[$activity->type])) {
                        $distanceKm = ($activity->distance / 1000); // Convert meters to km
                        $activityPoints = $distanceKm * $pointsPerKm[$activity->type];

                        // Initialize user points if not already set
                        if (!isset($userPoints[$activity->user_id])) {
                            $userPoints[$activity->user_id] = [
                                'user_id' => $activity->user_id,
                                'total_points' => 0,
                                // We'll fetch user details later
                            ];
                        }
                        // Add points to the user's total
                        $userPoints[$activity->user_id]['total_points'] += $activityPoints;
                    }
                }

                // Fetch user details for users who have points
                if (!empty($userPoints)) {
                    $userIds = array_keys($userPoints);
                    $users = $userModel->select('id, firstname, lastname') // Select necessary fields
                                       ->whereIn('id', $userIds)
                                       ->find();

                    // Create a map of user ID to user object for easy lookup
                    $userMap = [];
                    foreach ($users as $user) {
                        $userMap[$user->id] = $user;
                    }

                    // Add user details to the points array and round points
                    foreach ($userPoints as $userId => &$pointsData) { // Use reference '&' to modify directly
                        if (isset($userMap[$userId])) {
                            $pointsData['user'] = $userMap[$userId];
                            // Round total points to avoid long decimals
                            $pointsData['total_points'] = round($pointsData['total_points'], 2);
                        } else {
                            // Handle case where user might exist in activities but not users table (unlikely)
                            unset($userPoints[$userId]); // Remove if user details not found
                        }
                    }
                    unset($pointsData); // Unset reference

                    // Sort users by total points descending
                    usort($userPoints, function ($a, $b) {
                        // Sort descending
                        return $b['total_points'] <=> $a['total_points'];
                    });
                }
            }

        } catch (\Exception $e) {
            log_message('error', 'Error calculating points leaderboard: ' . $e->getMessage());
            // Optionally set an error message for the view
            session()->setFlashdata('error', 'Could not calculate points leaderboard.');
            $userPoints = []; // Ensure it's empty on error
        }

        // Pass the sorted user points data to the view
        $viewData = [
            'leaderboardData' => $userPoints,
        ];

        return view('points_leaderboard', $viewData); // Load the leaderboard view
    }
}
