<?php

namespace App\Controllers;

use App\Models\UserModel;
// No need for ActivityModel here if ChallengeService handles fetching
use App\Services\ChallengeService; // <-- Import the service
use App\Controllers\BaseController;

class PointsController extends BaseController
{
    /**
     * Displays the points leaderboard.
     */
    public function index()
    {
        $userModel = new UserModel();
        $challengeService = new ChallengeService(); // <-- Instantiate the service
        $leaderboardData = [];

        try {
            // Fetch all users (or maybe just those with activities?)
            // Consider performance for large numbers of users.
            $allUsers = $userModel->select('id, firstname, lastname')->findAll();

            if (!empty($allUsers)) {
                // Calculate score for each user using the service
                foreach ($allUsers as $user) {
                    $scoreData = $challengeService->calculateUserScore($user->id); // <-- Call service per user

                    // Only add users with points to the leaderboard (optional)
                    if ($scoreData['total_points'] > 0) {
                         $leaderboardData[] = [
                            'user' => $user, // Contains id, firstname, lastname
                            'total_points' => $scoreData['total_points']
                            // You could also add 'breakdown' => $scoreData['breakdown'] if needed
                        ];
                    }
                }

                // Sort users by total points descending
                if (!empty($leaderboardData)) {
                    usort($leaderboardData, function ($a, $b) {
                        return $b['total_points'] <=> $a['total_points']; // Descending sort
                    });
                }
            }

        } catch (\Exception $e) {
            log_message('error', 'Error calculating points leaderboard: ' . $e->getMessage());
            session()->setFlashdata('error', 'Could not calculate points leaderboard.');
            $leaderboardData = []; // Ensure it's empty on error
        }

        // Pass the sorted leaderboard data to the view
        $viewData = [
            'leaderboardData' => $leaderboardData,
        ];

        return view('points_leaderboard', $viewData); // Load the leaderboard view
    }
}
