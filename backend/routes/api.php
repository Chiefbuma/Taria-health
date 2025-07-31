<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AccountController;
use App\Http\Controllers\PatientNameController;
use App\Http\Controllers\MedicationController;
use App\Http\Controllers\PayerController;
use App\Http\Controllers\ClinicController;
use App\Http\Controllers\OnboardingController;
use App\Http\Controllers\MpesaPaymentController;
use App\Http\Controllers\InsuranceController;

// Public routes
Route::post('/register', [AccountController::class, 'register']);
Route::post('/login', [AccountController::class, 'login']);

// Protected routes
Route::middleware('auth:sanctum')->group(function () {
    // Account routes
    Route::get('/users', [AccountController::class, 'getUsers']);
    Route::get('/user', [AccountController::class, 'getUser']);
    Route::put('/users/{id}', [AccountController::class, 'updateUser']);
    
    // Patient Names routes
    Route::apiResource('patient-names', PatientNameController::class);
    Route::get('patient-names/search', [PatientNameController::class, 'search']);
    Route::post('/patient-names/{id}/activate', [PatientNameController::class, 'activate']);
    Route::post('/patient-names/{id}/deactivate', [PatientNameController::class, 'deactivate']);
    Route::get('/patient-names/active', [PatientNameController::class, 'activePatients']);
    Route::get('/patient-names/inactive', [PatientNameController::class, 'inactivePatients']);
    
    // Medication routes
    Route::apiResource('medications', MedicationController::class);
    
    // Payer routes
    Route::get('/payers', [PayerController::class, 'index']);

    // Clinic routes
    Route::get('/clinics', [ClinicController::class, 'index']);
    
    // Onboarding routes
    Route::post('/onboardings', [OnboardingController::class, 'store']);
    
    // Payment routes
    Route::prefix('payments')->group(function () {
        Route::post('/mpesa/initiate-payment', [MpesaPaymentController::class, 'initiate']);
        Route::post('/mpesa/verify-payment', [MpesaPaymentController::class, 'verify']);
    });
    
    // Insurance routes
    Route::post('/insurances', [InsuranceController::class, 'store']);
    Route::put('/insurances/{id}', [InsuranceController::class, 'update']);
});