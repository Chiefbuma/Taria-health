<?php
// routes/api.php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AccountController;
use App\Http\Controllers\ApplicationController;
use App\Http\Controllers\DocumentController;
use App\Http\Controllers\StaffController;
use App\Http\Controllers\DesignationController;
use App\Http\Controllers\ClinicController;

// Public routes
Route::post('/register', [AccountController::class, 'register']);
Route::post('/login', [AccountController::class, 'login']);

// Test route
Route::get('/test', function () {
    return response()->json(['message' => 'API is working!']);
});

// Authenticated routes
Route::middleware('auth:sanctum')->group(function () {
    
    // User routes
    Route::get('/user', [AccountController::class, 'getUser']);
    Route::get('/users', [AccountController::class, 'getUsers']);
    Route::put('/users/{id}', [AccountController::class, 'updateUser']);
    Route::delete('/users/delete', [AccountController::class, 'delete']);
    Route::post('/logout', [AccountController::class, 'logout']);

    // ===== PROFILE MANAGEMENT ROUTES =====
    Route::put('/profile', [AccountController::class, 'updateProfile']);
    Route::put('/change-password', [AccountController::class, 'changePassword']);

    // Application routes
    Route::get('/applications', [ApplicationController::class, 'index']);
    Route::post('/applications', [ApplicationController::class, 'store']);
    Route::get('/applications/{id}', [ApplicationController::class, 'show']);
    Route::put('/applications/{id}', [ApplicationController::class, 'update']);
    Route::delete('/applications/{id}', [ApplicationController::class, 'destroy']);
    Route::post('/applications/{id}/status', [ApplicationController::class, 'updateStatus']);
    Route::post('/applications/bulk-status', [ApplicationController::class, 'bulkUpdateStatus']);
    
    // ===== DISBURSEMENT AND RECEIPT CONFIRMATION ROUTES =====
    Route::put('/applications/{application}/disbursement-confirmation', [ApplicationController::class, 'updateDisbursementConfirmation']);
    Route::put('/applications/{id}/receipt-confirmation', [ApplicationController::class, 'updateReceiptConfirmation']);
    
    // Additional application routes
    Route::get('/my-applications', [ApplicationController::class, 'getUserApplications']);
    Route::get('/applications/user/{userId}', [ApplicationController::class, 'getUserApplicationsById']);
    Route::get('/pending-applications', [ApplicationController::class, 'getPendingApplications']);
    
    // Dashboard routes
    Route::get('/dashboard/stats', [ApplicationController::class, 'getDashboardStats']);
    Route::get('/dashboard/recent-applications', [ApplicationController::class, 'getRecentApplications']);

    // Document routes
    Route::get('/documents/application/{applicationId}', [DocumentController::class, 'getApplicationDocuments']);
    Route::get('/documents/{document}/download', [DocumentController::class, 'download']);
    Route::delete('/documents/{document}', [DocumentController::class, 'destroy']);

    // Staff routes - Enhanced for staff number lookup
    Route::get('/staff', [StaffController::class, 'index']);
    Route::post('/staff', [StaffController::class, 'store']);
    Route::get('/staff/{identifier}', [StaffController::class, 'show']); // Accepts both ID and staff_number
    Route::get('/staff/by-staff-number/{staffNumber}', [StaffController::class, 'getByStaffNumber']); // Specific staff number lookup
    Route::put('/staff/{id}', [StaffController::class, 'update']);
    Route::delete('/staff/{id}', [StaffController::class, 'destroy']);
    // Staff management routes
    Route::get('/staff/active', [StaffController::class, 'activeStaff']);
    Route::get('/staff/inactive', [StaffController::class, 'inactiveStaff']);
    Route::patch('/staff/{id}/activate', [StaffController::class, 'activate']);
    Route::patch('/staff/{id}/deactivate', [StaffController::class, 'deactivate']);

    // Designation routes
    Route::get('/designations', [DesignationController::class, 'index']);
    Route::post('/designations', [DesignationController::class, 'store']);
    Route::get('/designations/{id}', [DesignationController::class, 'show']);
    Route::put('/designations/{id}', [DesignationController::class, 'update']);
    Route::delete('/designations/{id}', [DesignationController::class, 'destroy']);

    // Clinic/Business Unit routes
    Route::get('/clinics', [ClinicController::class, 'index']);
    Route::post('/clinics', [ClinicController::class, 'store']);
    Route::get('/clinics/{id}', [ClinicController::class, 'show']);
    Route::put('/clinics/{id}', [ClinicController::class, 'update']);
    Route::delete('/clinics', [ClinicController::class, 'destroy']);
    Route::post('/clinics/activate', [ClinicController::class, 'activate']);
    Route::post('/clinics/deactivate', [ClinicController::class, 'deactivate']);

    // Analytics routes
    Route::get('/analytics/applications', [ApplicationController::class, 'getAnalyticsData']);
    Route::get('/analytics/users', [UserController::class, 'getUsersForAnalytics']);
});