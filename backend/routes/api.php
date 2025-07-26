<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AccountController;

// Public routes
Route::post('/register', [AccountController::class, 'register']);
Route::post('/login', [AccountController::class, 'login']);

// Protected routes
Route::middleware('auth:sanctum')->group(function () {
    Route::get('/users', [AccountController::class, 'getUsers']);
    Route::get('/user', [AccountController::class, 'getUser']);
});