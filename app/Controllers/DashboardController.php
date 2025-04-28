<?php

namespace App\Controllers;

use App\Models\ActivityModel; // <-- IMPORT YOUR ACTIVITY MODEL
use App\Models\UserModel;    // <-- Import UserModel if needed for user details
use CodeIgniter\Controller;

class DashboardController extends Controller
{
    public function index()
    {
        // Check if user is logged in
        if (!session()->get('isLoggedIn')) {
            return redirect()->to('/login');
        }

        // Fetch basic user data from session (set during login)
        $sessionUser = [
            'id'          => session()->get('id'), // Your internal user ID
            'strava_id'   => session()->get('strava_id'),
            'firstname'   => session()->get('firstname'),
            'lastname'    => session()->get('lastname'),
            'profile_pic' => session()->get('profile_pic'),
        ];

        // --- Fetch Activities from LOCAL DATABASE ---
        // The WebhookController is responsible for keeping this data up-to-date.
        $activityModel = new ActivityModel();
        $activities = [];
        $errorMessage = null;

        try {
            // Fetch recent activities for the logged-in user, ordered by start date descending
            $activities = $activityModel
                ->where('user_id', $sessionUser['id']) // Filter by your internal user ID
                ->orderBy('start_date', 'DESC')      // Show newest activities first
                ->limit(20)                           // Limit the number of activities shown initially
                ->findAll();

             log_message('debug', "Fetched " . count($activities) . " activities from local DB for User ID: {$sessionUser['id']}");

        } catch (\Exception $e) {
            log_message('error', "Database error fetching local activities for User ID {$sessionUser['id']}: " . $e->getMessage());
            // Set an error message to display to the user in the view
            $errorMessage = "Could not load activities at this time. Please try again later.";
            // You might want to set flashdata instead:
            // session()->setFlashdata('error', 'Could not load activities...');
        }

        // --- Optionally: Check if user is still authorized with Strava ---
        // You might want to fetch the full user record to check the 'is_strava_authorized' flag
        // $userModel = new UserModel();
        // $currentUser = $userModel->find($sessionUser['id']);
        // $isAuthorized = $currentUser ? $currentUser['is_strava_authorized'] : false;
        // Pass $isAuthorized to the view if you want to show a message or re-auth link

        // Pass the user data and locally fetched activities to the view
        $data = [
            'user' => $sessionUser, // Pass session data (or $currentUser if fetched)
            'activities' => $activities,
            'errorMessage' => $errorMessage
            // 'isAuthorized' => $isAuthorized // Pass authorization status if checked
        ];

        return view('dashboard', $data); // Assumes view file at app/Views/dashboard.php
    }
}
