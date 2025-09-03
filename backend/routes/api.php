<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AccountController;
use App\Http\Controllers\MedicationController;
use App\Http\Controllers\PayerController;
use App\Http\Controllers\ClinicController;
use App\Http\Controllers\OnboardingController;
use App\Http\Controllers\AdminOnboardingController;
use App\Http\Controllers\InsuranceController;
use App\Http\Controllers\WeeklyAssessmentController;
use App\Http\Controllers\ThreeMonthlyAssessmentController;
use App\Http\Controllers\SixMonthlyAssessmentController;
use App\Http\Controllers\GroupAssessmentController;
use App\Http\Controllers\SchemeController;
use App\Http\Controllers\AdminUserController;
use App\Http\Controllers\MpesaPaymentController;
use App\Http\Controllers\MedicationUseController;

// Public routes
Route::post('/register', [AccountController::class, 'register']);
Route::post('/login', [AccountController::class, 'login']);

Route::middleware('auth:sanctum')->group(function () {
    // User routes
    Route::get('/users', [AccountController::class, 'getUsers']);
    Route::get('/user', [AccountController::class, 'getUser']);
    Route::put('/users/{id}', [AccountController::class, 'updateUser']);
    Route::delete('/user/delete', [AccountController::class, 'delete']);
    Route::post('/admin/users', [AdminUserController::class, 'store']);
    Route::put('/admin/users/{id}', [AdminUserController::class, 'update']);
    
    // Insurance routes
    Route::get('/insurances', [InsuranceController::class, 'index']);
    Route::get('/insurances/{id}', [InsuranceController::class, 'show']);
    Route::post('/insurances', [InsuranceController::class, 'store']);
    Route::match(['put', 'post'], '/insurances/{id}', [InsuranceController::class, 'update']);
    Route::delete('/insurances/{id}', [InsuranceController::class, 'destroy']);
    Route::post('/insurances/{id}/approve', [InsuranceController::class, 'approve']);
    
    // Mpesa payment routes
    Route::post('/mpesa/initiate-payment', [MpesaPaymentController::class, 'initiate']);
    
    // Payer routes
    Route::get('/payers', [PayerController::class, 'index']);
    Route::get('/payers/{id}', [PayerController::class, 'show']);
    Route::post('/payers', [PayerController::class, 'store']);
    Route::put('/payers/{id}', [PayerController::class, 'update']);
    Route::delete('/payer/delete', [PayerController::class, 'destroy']);
    Route::post('/payer/activate', [PayerController::class, 'activate']);
    Route::post('/payer/deactivate', [PayerController::class, 'deactivate']);

    // Clinic routes
    Route::get('/clinics', [ClinicController::class, 'index']);
    Route::get('/clinics/{id}', [ClinicController::class, 'show']);
    Route::post('/clinics', [ClinicController::class, 'store']);
    Route::put('/clinics/{id}', [ClinicController::class, 'update']);
    Route::delete('/clinic/delete', [ClinicController::class, 'destroy']);
    Route::post('/clinic/activate', [ClinicController::class, 'activate']);
    Route::post('/clinic/deactivate', [ClinicController::class, 'deactivate']);
    
    // User Onboarding routes
    Route::post('/onboardings', [OnboardingController::class, 'store']);
    Route::get('/onboarding/check/{user_id}', [OnboardingController::class, 'check']);
    
    // Admin Onboarding routes
    Route::get('/adminonboardings', [AdminOnboardingController::class, 'index']);
    Route::get('/adminonboardings/{id}', [AdminOnboardingController::class, 'show']);
    Route::post('/adminonboardings', [AdminOnboardingController::class, 'store']);
    Route::put('/adminonboardings/{id}', [AdminOnboardingController::class, 'update']);
    Route::delete('/adminonboardings/{id}', [AdminOnboardingController::class, 'destroy']);
    Route::post('/adminonboardings/{id}/complete', [AdminOnboardingController::class, 'complete']);
    
    // Assessment routes
    Route::get('/weekly-assessments', [WeeklyAssessmentController::class, 'index']);
    Route::post('/weekly-assessments', [WeeklyAssessmentController::class, 'store']);
    Route::get('/weekly-assessments/{id}', [WeeklyAssessmentController::class, 'show']);
    Route::put('/weekly-assessments/{id}', [WeeklyAssessmentController::class, 'update']);
    Route::delete('/weekly-assessments/{id}', [WeeklyAssessmentController::class, 'destroy']);

    Route::get('/three-monthly-assessments', [ThreeMonthlyAssessmentController::class, 'index']);
    Route::post('/three-monthly-assessments', [ThreeMonthlyAssessmentController::class, 'store']);
    Route::get('/three-monthly-assessments/{id}', [ThreeMonthlyAssessmentController::class, 'show']);
    Route::put('/three-monthly-assessments/{id}', [ThreeMonthlyAssessmentController::class, 'update']);
    Route::delete('/three-monthly-assessments/{id}', [ThreeMonthlyAssessmentController::class, 'destroy']);

    Route::get('/six-monthly-assessments', [SixMonthlyAssessmentController::class, 'index']);
    Route::post('/six-monthly-assessments', [SixMonthlyAssessmentController::class, 'store']);
    Route::get('/six-monthly-assessments/{id}', [SixMonthlyAssessmentController::class, 'show']);
    Route::put('/six-monthly-assessments/{id}', [SixMonthlyAssessmentController::class, 'update']);
    Route::delete('/six-monthly-assessments/{id}', [SixMonthlyAssessmentController::class, 'destroy']);

    Route::get('/group-assessments', [GroupAssessmentController::class, 'getGroupAssessments']);
    Route::get('/onboarding-columns', [GroupAssessmentController::class, 'getOnboardingColumns']);

    // Scheme routes
    Route::get('/schemes', [SchemeController::class, 'index']);
    Route::get('/schemes/active', [SchemeController::class, 'activeSchemes']);
    Route::get('/schemes/{id}', [SchemeController::class, 'show']);
    Route::post('/schemes', [SchemeController::class, 'store']);
    Route::put('/schemes/{id}', [SchemeController::class, 'update']);
    Route::delete('/schemes/{id}', [SchemeController::class, 'destroy']);

    // User onboarding data
    Route::get('/user/onboarding', [AdminOnboardingController::class, 'getUserOnboarding']);
    
    // Medication routes
    Route::get('/medications', [MedicationController::class, 'index']);
    Route::post('/medications', [MedicationController::class, 'store']);
    Route::get('/medications/{id}', [MedicationController::class, 'show']);
    Route::put('/medications/{id}', [MedicationController::class, 'update']);
    Route::delete('/medications/{id}', [MedicationController::class, 'destroy']);

    Route::get('/medications-uses', [MedicationUseController::class, 'getMedications']);
    Route::get('/adminonboardings', [MedicationUseController::class, 'getOnboardings']);
    Route::get('/onboardings/{onboardingId}/medication-uses', [MedicationUseController::class, 'index']);
    Route::post('/onboardings/{onboardingId}/medication-uses', [MedicationUseController::class, 'store']);
    Route::put('/medication-uses/{id}', [MedicationUseController::class, 'update']);
    Route::delete('/medication-uses/{id}', [MedicationUseController::class, 'destroy']);
});