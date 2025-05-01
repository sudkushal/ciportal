<?php

namespace App\Controllers;

use App\Controllers\BaseController;
use App\Models\ActivityModel; // Import the ActivityModel
use App\Models\UserModel;     // Import the UserModel

class DashboardController extends BaseController
{
    public function index()
    {
        // Check if user is logged in
        if (!session()->get('isLoggedIn')) {
            return redirect()->to('/');
        }

        // Get current user ID from session
        $currentUserId = session()->get('user_id');

        // Instantiate models
        $activityModel = new ActivityModel();
        $userModel = new UserModel();

        $recentActivities = [];
        $currentUserRank = null;
        $currentUserPoints = 0; // Default to 0

        try {
            // --- Fetch Recent Activities for display ---
            $recentActivities = $activityModel
                ->where('user_id', $currentUserId)
                ->orderBy('start_date', 'DESC')
                ->limit(10)
                ->find();

            // --- Calculate Points and Rank ---
            $pointsPerKm = [
                'Walk' => 5.0,
                'Run'  => 5.0,
                'Ride' => 1.25,
            ];
            $relevantActivityTypes = array_keys($pointsPerKm);
            $userPoints = [];

            // Fetch all relevant activities for all users to calculate ranks
            $allActivities = $activityModel
                ->whereIn('type', $relevantActivityTypes)
                ->select('user_id, type, distance')
                ->findAll();

            if (!empty($allActivities)) {
                // Aggregate points per user
                foreach ($allActivities as $activity) {
                    if (isset($activity->distance) && isset($pointsPerKm[$activity->type])) {
                        $distanceKm = ($activity->distance / 1000);
                        $activityPoints = $distanceKm * $pointsPerKm[$activity->type];
                        if (!isset($userPoints[$activity->user_id])) {
                            $userPoints[$activity->user_id] = ['user_id' => $activity->user_id, 'total_points' => 0];
                        }
                        $userPoints[$activity->user_id]['total_points'] += $activityPoints;
                    }
                }

                // Round points
                 foreach ($userPoints as &$pointsData) {
                     $pointsData['total_points'] = round($pointsData['total_points'], 2);
                 }
                 unset($pointsData);

                // Sort users by points descending to determine rank
                uasort($userPoints, function ($a, $b) {
                    return $b['total_points'] <=> $a['total_points'];
                });

                // Find current user's rank and points
                $rank = 1;
                foreach ($userPoints as $userId => $data) {
                    if ($userId == $currentUserId) {
                        $currentUserRank = $rank;
                        $currentUserPoints = $data['total_points'];
                        break; // Found the user, no need to continue loop
                    }
                    $rank++;
                }
            }
            // If the user has no points yet, $currentUserRank will remain null

        } catch (\Exception $e) {
            log_message('error', 'Error fetching dashboard data for user ID ' . $currentUserId . ': ' . $e->getMessage());
            session()->setFlashdata('error', 'Could not load dashboard data.');
        }

        // --- Prepare Data for View ---
        $viewData = [
            'firstname'           => session()->get('firstname'),
            'lastname'            => session()->get('lastname'),
            'profile_picture_url' => session()->get('profile_picture_url'),
            'strava_id'           => session()->get('strava_id'),
            'activities'          => $recentActivities, // Recent activities for display
            'currentUserRank'     => $currentUserRank,  // User's calculated rank
            'currentUserPoints'   => $currentUserPoints // User's calculated points
        ];

        // Load the dashboard view and pass the combined data
        return view('dashboard', $viewData);
    }

     /**
     * Basic logout function.
     */
    public function logout()
    {
        session()->destroy();
        return redirect()->to('/')->with('message', 'You have been logged out.');
    }
}
