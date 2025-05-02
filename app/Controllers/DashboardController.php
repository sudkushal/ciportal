<?php

namespace App\Controllers;

use App\Controllers\BaseController;
use App\Models\ActivityModel;
use App\Models\UserModel;
use App\Services\ChallengeService; // <-- Import the service

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

        // Instantiate models and service
        $activityModel = new ActivityModel();
        $userModel = new UserModel(); // Needed to get all users for ranking
        $challengeService = new ChallengeService(); // <-- Instantiate the service

        $recentActivities = [];
        $currentUserRank = null; // Initialize rank
        $currentUserPoints = 0;
        $pointsBreakdown = []; // To store breakdown per stage/challenge

        try {
            // --- Fetch Recent Activities for display ---
            $recentActivities = $activityModel
                ->where('user_id', $currentUserId)
                ->orderBy('start_date', 'DESC')
                ->limit(10)
                ->find();

            // --- Calculate Score for the Current User ---
            $scoreData = $challengeService->calculateUserScore($currentUserId); // <-- Call the service
            $currentUserPoints = $scoreData['total_points'];
            $pointsBreakdown = $scoreData['breakdown']; // Get the breakdown

            // --- Calculate Rank (Requires scoring all users) ---
            // WARNING: This can be slow with many users. Consider caching.
            $allUsers = $userModel->select('id')->findAll(); // Fetch only IDs for efficiency
            $allUserScores = [];

            if (!empty($allUsers)) {
                foreach ($allUsers as $user) {
                    // Calculate score for each user
                    $userScoreData = $challengeService->calculateUserScore($user->id);
                    // Store only if they have points, include user ID
                    if ($userScoreData['total_points'] > 0) {
                         $allUserScores[] = [
                            'user_id' => $user->id,
                            'total_points' => $userScoreData['total_points']
                         ];
                    }
                }

                 // Sort all scores descending
                 if(!empty($allUserScores)) {
                    usort($allUserScores, function ($a, $b) {
                        return $b['total_points'] <=> $a['total_points']; // Descending sort
                    });

                    // Find the rank of the current user
                    $rank = 1;
                    foreach ($allUserScores as $scoreEntry) {
                        if ($scoreEntry['user_id'] == $currentUserId) {
                            $currentUserRank = $rank;
                            break; // Found the rank
                        }
                        $rank++;
                    }
                 }
            }
             // If the user has 0 points, $currentUserRank will remain null


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
            'activities'          => $recentActivities,
            'currentUserRank'     => $currentUserRank, // Pass the calculated rank
            'currentUserPoints'   => $currentUserPoints, // Pass calculated points
            'pointsBreakdown'     => $pointsBreakdown,   // Pass the breakdown (optional for view)
        ];

        // Load the dashboard view
        return view('dashboard', $viewData);
    }

    public function logout()
    {
        session()->destroy();
        return redirect()->to('/')->with('message', 'You have been logged out.');
    }
}
