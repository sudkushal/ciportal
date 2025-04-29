<?php

use CodeIgniter\Router\RouteCollection;

/**
 * @var RouteCollection $routes
 */
$routes->get('/', 'Home::index');
// In app/Config/Routes.php



// Dashboard route (requires user to be logged in - handled by controller)
$routes->get('/dashboard', 'App\Controllers\DashboardController::index');

// Logout route
$routes->get('/logout', 'App\Controllers\DashboardController::logout');

$routes->get('/login', 'SAC::login');
$routes->get('strava/callback', 'SAC::callback');
