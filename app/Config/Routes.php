<?php

use CodeIgniter\Router\RouteCollection;

/**
 * @var RouteCollection $routes
 */
$routes->get('/', 'Home::index');

$routes->get('/strava/login', 'App\Controllers\StravaAuthController::login');
$routes->get('/strava/callback', 'App\Controllers\StravaAuthController::callback');

// Dashboard route (requires user to be logged in - handled by controller)
$routes->get('/dashboard', 'App\Controllers\DashboardController::index');

// Logout route
$routes->get('/logout', 'App\Controllers\DashboardController::logout');
