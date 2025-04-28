<?php

use CodeIgniter\Router\RouteCollection;

/**
 * @var RouteCollection $routes
 */
$routes->get('/', 'Home::index');
$routes->get('/login', 'AuthController::login');
$routes->get('/logout', 'AuthController::logout');
$routes->get('/strava-redirect', 'AuthController::stravaRedirect');
$routes->get('/callback', 'AuthController::stravaCallback');

// Later for dashboard
$routes->get('/dashboard', 'DashboardController::index');
