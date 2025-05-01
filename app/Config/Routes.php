<?php

use CodeIgniter\Router\RouteCollection;

/**
 * @var RouteCollection $routes
 */
$routes->get('/', 'Home::index');
// In app/Config/Routes.php



// Dashboard route (requires user to be logged in - handled by controller)
$routes->get('/dashboard', 'DashboardController::index');

// Routes for the simple AuthController
$routes->get('/login', 'AuthController::login');         // To start the login process
$routes->get('/auth/callback', 'AuthController::callback'); // Strava redirects back here
$routes->get('/logout', 'AuthController::logout');        // To log the user out

