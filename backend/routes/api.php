<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AccountController;
use App\Http\Controllers\MedicationController;
use App\Http\Controllers\PayerController;
use App\Http\Controllers\ClinicController;
use App\Http\Controllers\OnboardingController;
use App\Http\Controllers\AdminOnboardingController;
use App\Http\Controllers\WeeklyAssessmentController;
use App\Http\Controllers\ThreeMonthlyAssessmentController;
use App\Http\Controllers\SixMonthlyAssessmentController;
use App\Http\Controllers\GroupAssessmentController;
use App\Http\Controllers\SchemeController;
use App\Http\Controllers\AdminUserController;
use App\Http\Controllers\MedicationUseController;
use App\Http\Controllers\InsuranceController;

// Public routes
Route::post('/register', [AccountController::class, 'register'])->name('auth.register');
Route::post('/login', [AccountController::class, 'login'])->name('auth.login');

// Authenticated routes
Route::middleware('auth:sanctum')->group(function () {
    // User routes
    Route::prefix('users')->group(function () {
        Route::get('/', [AccountController::class, 'getUsers'])->name('users.index');
        Route::get('/me', [AccountController::class, 'getUser'])->name('users.me');
        Route::put('/{id}', [AccountController::class, 'updateUser'])->name('users.update');
        Route::delete('/delete', [AccountController::class, 'delete'])->name('users.delete');
    });

    // Admin User routes
    Route::prefix('admin/users')->group(function () {
        Route::post('/', [AdminUserController::class, 'store'])->name('admin.users.store');
        Route::put('/{id}', [AdminUserController::class, 'update'])->name('admin.users.update');
    });

    // Payer routes
    Route::prefix('payers')->group(function () {
        Route::get('/', [PayerController::class, 'index'])->name('payers.index');
        Route::get('/{id}', [PayerController::class, 'show'])->name('payers.show');
        Route::post('/', [PayerController::class, 'store'])->name('payers.store');
        Route::put('/{id}', [PayerController::class, 'update'])->name('payers.update');
        Route::delete('/delete', [PayerController::class, 'destroy'])->name('payers.delete');
        Route::post('/activate', [PayerController::class, 'activate'])->name('payers.activate');
        Route::post('/deactivate', [PayerController::class, 'deactivate'])->name('payers.deactivate');
    });

    // Clinic routes
    Route::prefix('clinics')->group(function () {
        Route::get('/', [ClinicController::class, 'index'])->name('clinics.index');
        Route::get('/{id}', [ClinicController::class, 'show'])->name('clinics.show');
        Route::post('/', [ClinicController::class, 'store'])->name('clinics.store');
        Route::put('/{id}', [ClinicController::class, 'update'])->name('clinics.update');
        Route::delete('/delete', [ClinicController::class, 'destroy'])->name('clinics.delete');
        Route::post('/activate', [ClinicController::class, 'activate'])->name('clinics.activate');
        Route::post('/deactivate', [ClinicController::class, 'deactivate'])->name('clinics.deactivate');
    });

    // Onboarding routes (self-onboarding)
    Route::prefix('onboardings')->group(function () {
        Route::post('/', [OnboardingController::class, 'store'])->name('onboardings.store');
        Route::get('/check/{user_id}', [OnboardingController::class, 'check'])->name('onboardings.check');

    });

    // Admin Onboarding routes
    Route::prefix('adminonboardings')->group(function () {
        Route::get('/', [AdminOnboardingController::class, 'index'])->name('adminonboardings.index');
        Route::get('/{id}', [AdminOnboardingController::class, 'show'])->name('adminonboardings.show');
        Route::post('/payer/adminboarding', [AdminOnboardingController::class, 'store'])->name('adminonboardings.store');
        Route::put('/{id}', [AdminOnboardingController::class, 'update'])->name('adminonboardings.update');
        Route::delete('/{id}', [AdminOnboardingController::class, 'destroy'])->name('adminonboardings.delete');
        Route::post('/{id}/complete', [AdminOnboardingController::class, 'complete'])->name('adminonboardings.complete');
        Route::put('/user/onboarding', [AdminOnboardingController::class, 'updateUserOnboarding'])->name('adminonboardings.user.update');
    });

    // User Onboarding routes
    Route::get('/user/onboarding', [AdminOnboardingController::class, 'getUserOnboarding'])->name('user.onboarding.get');
    Route::post('/user/onboarding', [AdminOnboardingController::class, 'updateUserOnboarding'])->name('user.onboarding.create');

    // Insurance routes
    Route::prefix('insurance')->group(function () {
        Route::get('/', [InsuranceController::class, 'index'])->name('insurance.index');
        Route::get('/{id}', [InsuranceController::class, 'show'])->name('insurance.show');
        Route::post('/', [InsuranceController::class, 'store'])->name('insurance.store');
        Route::put('/{id}', [InsuranceController::class, 'update'])->name('insurance.update');
        Route::delete('/{id}/document', [InsuranceController::class, 'deleteDocument'])->name('insurance.document.delete');
    });

    // Assessment routes
    Route::prefix('weekly-assessments')->group(function () {
        Route::get('/', [WeeklyAssessmentController::class, 'index'])->name('weekly-assessments.index');
        Route::post('/', [WeeklyAssessmentController::class, 'store'])->name('weekly-assessments.store');
        Route::get('/{id}', [WeeklyAssessmentController::class, 'show'])->name('weekly-assessments.show');
        Route::put('/{id}', [WeeklyAssessmentController::class, 'update'])->name('weekly-assessments.update');
        Route::delete('/{id}', [WeeklyAssessmentController::class, 'destroy'])->name('weekly-assessments.delete');
    });

    Route::prefix('three-monthly-assessments')->group(function () {
        Route::get('/', [ThreeMonthlyAssessmentController::class, 'index'])->name('three-monthly-assessments.index');
        Route::post('/', [ThreeMonthlyAssessmentController::class, 'store'])->name('three-monthly-assessments.store');
        Route::get('/{id}', [ThreeMonthlyAssessmentController::class, 'show'])->name('three-monthly-assessments.show');
        Route::put('/{id}', [ThreeMonthlyAssessmentController::class, 'update'])->name('three-monthly-assessments.update');
        Route::delete('/{id}', [ThreeMonthlyAssessmentController::class, 'destroy'])->name('three-monthly-assessments.delete');
    });

    Route::get('/six-monthly-assessments', [SixMonthlyAssessmentController::class, 'index']);
    Route::post('/six-monthly-assessments', [SixMonthlyAssessmentController::class, 'store']);
    Route::get('/six-monthly-assessments/{id}', [SixMonthlyAssessmentController::class, 'show']);
    Route::put('/six-monthly-assessments/{id}', [SixMonthlyAssessmentController::class, 'update']);
    Route::delete('/six-monthly-assessments/{id}', [SixMonthlyAssessmentController::class, 'destroy']);


    // Group Assessments routes
    Route::prefix('group-assessments')->group(function () {
        Route::get('/', [GroupAssessmentController::class, 'getGroupAssessments'])->name('group-assessments.index');
        Route::get('/onboarding-columns', [GroupAssessmentController::class, 'getOnboardingColumns'])->name('group-assessments.onboarding-columns');
    });

    // Scheme routes
    Route::prefix('schemes')->group(function () {
        Route::get('/', [SchemeController::class, 'index'])->name('schemes.index');
        Route::get('/active', [SchemeController::class, 'activeSchemes'])->name('schemes.active');
        Route::get('/{id}', [SchemeController::class, 'show'])->name('schemes.show');
        Route::post('/', [SchemeController::class, 'store'])->name('schemes.store');
        Route::put('/{id}', [SchemeController::class, 'update'])->name('schemes.update');
        Route::delete('/{id}', [SchemeController::class, 'destroy'])->name('schemes.delete');
    });

    // Medication Use routes
    Route::prefix('onboardings/{onboardingId}/medication-uses')->group(function () {
        Route::get('/', [MedicationUseController::class, 'index'])->name('medication-uses.index');
        Route::post('/', [MedicationUseController::class, 'store'])->name('medication-uses.store');
    });

    Route::prefix('medication-uses')->group(function () {
        Route::put('/{medicationUse}', [MedicationUseController::class, 'update'])->name('medication-uses.update');
        Route::delete('/{medicationUse}', [MedicationUseController::class, 'destroy'])->name('medication-uses.delete');
    });

    // Medication routes
    Route::prefix('medications')->group(function () {
        Route::get('/', [MedicationController::class, 'index'])->name('medications.index');
        Route::post('/', [MedicationController::class, 'store'])->name('medications.store');
        Route::get('/{id}', [MedicationController::class, 'show'])->name('medications.show');
        Route::put('/{id}', [MedicationController::class, 'update'])->name('medications.update');
        Route::delete('/{id}', [MedicationController::class, 'destroy'])->name('medications.delete');
    });

    //Insurance routes
    Route::prefix('admin/onboardings')->group(function () {
        Route::get('/', [InsuranceController::class, 'index'])->name('admin.onboardings.index');
        Route::get('/{id}', [InsuranceController::class, 'show'])->name('admin.onboardings.show');
        Route::post('/insurance', [InsuranceController::class, 'store'])->name('admin.onboardings.insurance.store');
        Route::put('/{id}/insurance', [InsuranceController::class, 'update'])->name('admin.onboardings.insurance.update');
        Route::delete('/{id}/document', [InsuranceController::class, 'deleteDocument'])->name('admin.onboardings.document.delete');
    });
});