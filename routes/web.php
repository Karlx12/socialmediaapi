<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

// Note: API routes are loaded automatically by Laravel's RouteServiceProvider, so we don't
// require them here to avoid double registration and middleware collisions.
