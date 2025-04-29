<?php

namespace App\Controllers;

use App\Controllers\BaseController;

class DashboardController extends BaseController
{
    public function index()
    {
        // Check if user is logged in by verifying session data
        if (!session()->get('isLoggedIn')) {
            // User is not logged in, redirect to the home page (or login page)
            return redirect()->to('/');
        }

        // User is logged in, retrieve data from session
        $userData = [
            'firstname'           => session()->get('firstname'),
            'lastname'            => session()->get('lastname'),
            'profile_picture_url' => session()->get('profile_picture_url'),
            'strava_id'           => session()->get('strava_id'),
            // Add any other data needed for the dashboard view
        ];

        // Load the dashboard view and pass the user data
        return view('dashboard', $userData); // Assumes view file is named 'dashboard.php'
    }

     /**
     * Basic logout function.
     */
    public function logout()
    {
        // Destroy the session
        session()->destroy();
        // Redirect to the home page with a success message
        return redirect()->to('/')->with('message', 'You have been logged out.');
    }
}
