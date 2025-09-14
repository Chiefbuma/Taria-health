<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\Onboarding;
use App\Models\Mpesa;
use App\Models\Insurance;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class AdminOnboardingController extends Controller
{
    /**
     * Get all onboardings (admin, navigator, payer, user, claims).
     */
    public function index(Request $request)
    {
        try {
            $user = $request->user();
            Log::debug('index debug:', [
                'user_exists' => !empty($user),
                'user_id' => $user?->id,
                'role' => $user?->role,
                'is_active' => $user?->isActive(),
            ]);
            if (!$user) {
                Log::warning('No authenticated user found for onboarding access');
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized'
                ], 403);
            }
            if (!$user->isActive()) {
                Log::warning('Inactive user attempted to access onboarding:', ['user_id' => $user->id, 'role' => $user->role]);
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized'
                ], 403);
            }
            $allowedRoles = [
                User::ROLE_ADMIN,
                User::ROLE_NAVIGATOR,
                User::ROLE_PAYER,
                User::ROLE_USER,
                User::ROLE_CLAIMS
            ];
            if (!in_array($user->role, $allowedRoles)) {
                Log::warning('Unauthorized role for fetching onboardings:', ['user_id' => $user->id, 'role' => $user->role]);
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized'
                ], 403);
            }

            $query = Onboarding::query();

            // Restrict payers to only see onboardings associated with their payer_id
            if ($user->role === User::ROLE_PAYER) {
                if (!$user->payer_id) {
                    Log::warning('Payer has no payer_id:', ['user_id' => $user->id]);
                    return response()->json([
                        'success' => false,
                        'message' => 'Payer ID not set for this user'
                    ], 403);
                }
                $query->where('payer_id', $user->payer_id);
            }
            // Restrict users to only see their own onboardings
            elseif ($user->role === User::ROLE_USER) {
                $query->where('user_id', $user->id);
            }

            $onboardings = $query->get();
            Log::info('Onboardings retrieved successfully:', ['user_id' => $user->id, 'role' => $user->role, 'count' => $onboardings->count()]);
            return response()->json([
                'success' => true,
                'message' => 'Onboardings retrieved successfully',
                'onboardings' => $onboardings
            ], 200);
        } catch (\Exception $e) {
            Log::error('Error fetching onboardings:', ['user_id' => $request->user()?->id, 'role' => $request->user()?->role, 'error' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch onboardings',
                'error' => env('APP_DEBUG') ? $e->getMessage() : null
            ], 500);
        }
    }

    /**
     * Get a specific onboarding (admin, navigator, payer, user, claims).
     */
    public function show(Request $request, $id)
    {
        try {
            $user = $request->user();
            Log::debug('show debug:', [
                'user_exists' => !empty($user),
                'user_id' => $user?->id,
                'role' => $user?->role,
                'is_active' => $user?->isActive(),
                'onboarding_id' => $id
            ]);
            if (!$user) {
                Log::warning('No authenticated user found for onboarding access');
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized'
                ], 403);
            }
            if (!$user->isActive()) {
                Log::warning('Inactive user attempted to access onboarding:', ['user_id' => $user->id, 'role' => $user->role, 'onboarding_id' => $id]);
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized'
                ], 403);
            }
            $allowedRoles = [
                User::ROLE_ADMIN,
                User::ROLE_NAVIGATOR,
                User::ROLE_PAYER,
                User::ROLE_USER,
                User::ROLE_CLAIMS
            ];
            if (!in_array($user->role, $allowedRoles)) {
                Log::warning('Unauthorized role for fetching onboarding:', ['user_id' => $user->id, 'role' => $user->role, 'onboarding_id' => $id]);
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized'
                ], 403);
            }

            $onboarding = Onboarding::findOrFail($id);

            // Restrict payers to only see their associated onboardings
            if ($user->role === User::ROLE_PAYER) {
                if (!$user->payer_id) {
                    Log::warning('Payer has no payer_id:', ['user_id' => $user->id, 'onboarding_id' => $id]);
                    return response()->json([
                        'success' => false,
                        'message' => 'Payer ID not set for this user'
                    ], 403);
                }
                if ($onboarding->payer_id !== $user->payer_id) {
                    Log::warning('Payer unauthorized to view onboarding:', ['user_id' => $user->id, 'onboarding_id' => $id]);
                    return response()->json([
                        'success' => false,
                        'message' => 'Unauthorized to view this onboarding'
                    ], 403);
                }
            }
            // Restrict users to only see their own onboarding
            elseif ($user->role === User::ROLE_USER && $onboarding->user_id !== $user->id) {
                Log::warning('User unauthorized to view onboarding:', ['user_id' => $user->id, 'onboarding_id' => $id]);
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized to view this onboarding'
                ], 403);
            }

            Log::info('Onboarding retrieved successfully:', ['user_id' => $user->id, 'role' => $user->role, 'onboarding_id' => $id]);
            return response()->json([
                'success' => true,
                'message' => 'Onboarding retrieved successfully',
                'onboarding' => $onboarding
            ], 200);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            Log::error('Onboarding not found:', ['onboarding_id' => $id, 'user_id' => $request->user()?->id, 'role' => $request->user()?->role]);
            return response()->json([
                'success' => false,
                'message' => 'Onboarding not found'
            ], 404);
        } catch (\Exception $e) {
            Log::error('Error fetching onboarding:', ['onboarding_id' => $id, 'user_id' => $request->user()?->id, 'role' => $request->user()?->role, 'error' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch onboarding',
                'error' => env('APP_DEBUG') ? $e->getMessage() : null
            ], 500);
        }
    }

    /**
     * Create a new onboarding (admin, navigator, payer, user, claims).
     */
    public function store(Request $request)
    {
        Log::info('Onboarding creation request received:', ['user_id' => $request->user()?->id, 'role' => $request->user()?->role, 'data' => $request->all()]);

        try {
            $user = $request->user();
            Log::debug('store debug:', [
                'user_exists' => !empty($user),
                'user_id' => $user?->id,
                'role' => $user?->role,
                'is_active' => $user?->isActive(),
            ]);
            if (!$user) {
                Log::warning('No authenticated user found for onboarding creation');
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized'
                ], 403);
            }
            if (!$user->isActive()) {
                Log::warning('Inactive user attempted to create onboarding:', ['user_id' => $user->id, 'role' => $user->role]);
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized'
                ], 403);
            }
            $allowedRoles = [
                User::ROLE_ADMIN,
                User::ROLE_NAVIGATOR,
                User::ROLE_PAYER,
                User::ROLE_USER,
                User::ROLE_CLAIMS
            ];
            if (!in_array($user->role, $allowedRoles)) {
                Log::warning('Unauthorized role for creating onboarding:', ['user_id' => $user->id, 'role' => $user->role]);
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized'
                ], 403);
            }

            if ($user->role === User::ROLE_PAYER && !$user->payer_id) {
                Log::warning('Payer has no payer_id:', ['user_id' => $user->id]);
                return response()->json([
                    'success' => false,
                    'message' => 'Payer ID not set for this user'
                ], 403);
            }

            // Verify payment
            $paymentVerified = false;
            $paymentMethod = $request->payment_method;
            $paymentId = $request->payment_id;

            if ($paymentMethod === 'mpesa') {
                $payment = Mpesa::where('id', $paymentId)
                    ->where('status', 'completed')
                    ->first();
                if (!$payment) {
                    Log::warning('M-Pesa payment verification failed:', ['user_id' => $user->id, 'payment_id' => $paymentId]);
                    return response()->json([
                        'success' => false,
                        'message' => 'M-Pesa payment not completed or not found'
                    ], 400);
                }
                $paymentVerified = true;
            } elseif ($paymentMethod === 'insurance') {
                $payment = Insurance::where('id', $paymentId)->first();
                if (!$payment) {
                    Log::warning('Insurance record verification failed:', ['user_id' => $user->id, 'payment_id' => $paymentId]);
                    return response()->json([
                        'success' => false,
                        'message' => 'Insurance record not found'
                    ], 400);
                }
                $paymentVerified = true;
            }

            if (!$paymentVerified) {
                Log::warning('Payment verification failed:', ['user_id' => $user->id, 'payment_method' => $paymentMethod, 'payment_id' => $paymentId]);
                return response()->json([
                    'success' => false,
                    'message' => 'Payment verification failed'
                ], 400);
            }

            // Create onboarding and update payment record in a transaction
            $onboarding = DB::transaction(function () use ($request, $paymentMethod, $paymentId, $user) {
                $onboarding = Onboarding::create([
                    'user_id' => $user->role === User::ROLE_USER ? $user->id : $request->user_id,
                    'first_name' => $request->first_name,
                    'middle_name' => $request->middle_name,
                    'last_name' => $request->last_name,
                    'date_of_birth' => $request->date_of_birth,
                    'emr_number' => $request->emr_number,
                    'payer_id' => $request->payer_id,
                    'clinic_id' => $request->clinic_id,
                    'diagnoses' => $request->diagnoses,
                    'age' => $request->age,
                    'sex' => $request->sex,
                    'date_of_onboarding' => now()->toDateString(),
                    'emergency_contact_name' => $request->emergency_contact_name,
                    'emergency_contact_phone' => $request->emergency_contact_phone,
                    'emergency_contact_relation' => $request->emergency_contact_relation,
                    'weight_loss_target' => $request->weight_loss_target,
                    'hba1c_target' => $request->hba1c_target,
                    'bp_target' => $request->bp_target,
                    'activity_goal' => $request->activity_goal,
                    'hba1c_baseline' => $request->hba1c_baseline,
                    'ldl_baseline' => $request->ldl_baseline,
                    'bp_baseline' => $request->bp_baseline,
                    'weight_baseline' => $request->weight_baseline,
                    'height' => $request->height,
                    'bmi_baseline' => $request->bmi_baseline,
                    'serum_creatinine_baseline' => $request->serum_creatinine_baseline,
                    'ecg_baseline' => $request->ecg_baseline,
                    'physical_activity_level' => $request->physical_activity_level,
                    'brief_medical_history' => $request->brief_medical_history,
                    'date_of_diagnosis' => $request->date_of_diagnosis,
                    'past_medical_interventions' => $request->past_medical_interventions,
                    'relevant_family_history' => $request->relevant_family_history,
                    'has_weighing_scale' => $request->has_weighing_scale,
                    'has_glucometer' => $request->has_glucometer,
                    'has_bp_machine' => $request->has_bp_machine,
                    'has_tape_measure' => $request->has_tape_measure,
                    'dietary_restrictions' => $request->dietary_restrictions,
                    'allergies_intolerances' => $request->allergies_intolerances,
                    'lifestyle_factors' => $request->lifestyle_factors,
                    'physical_limitations' => $request->physical_limitations,
                    'psychosocial_factors' => $request->psychosocial_factors,
                    'initial_consultation_date' => $request->initial_consultation_date,
                    'follow_up_review1' => $request->follow_up_review1,
                    'follow_up_review2' => $request->follow_up_review2,
                    'additional_review' => $request->additional_review,
                    'consent_date' => $request->consent_date,
                    'consent_to_telehealth' => $request->consent_to_telehealth,
                    'consent_to_risks' => $request->consent_to_risks,
                    'consent_to_data_use' => $request->consent_to_data_use,
                    'payment_method' => $paymentMethod,
                    'payment_id' => $paymentId,
                    'payment_status' => $request->payment_status ?? 'pending',
                    'mpesa_number' => $request->mpesa_number,
                    'mpesa_reference' => $request->mpesa_reference,
                    'insurance_provider' => $request->insurance_provider,
                    'insurance_id' => $request->insurance_id,
                    'policy_number' => $request->policy_number,
                    'activation_code' => $request->activation_code,
                    'is_active' => $request->is_active ?? true,
                    'hba1c_latest_reading_date' => $request->hba1c_latest_reading_date,
                    'ldl_latest_reading_date' => $request->ldl_latest_reading_date,
                    'bp_latest_reading_date' => $request->bp_latest_reading_date,
                    'weight_latest_reading_date' => $request->weight_latest_reading_date,
                    'serum_creatinine_latest_reading_date' => $request->serum_creatinine_latest_reading_date,
                    'ecg_latest_reading_date' => $request->ecg_latest_reading_date,
                ]);

                // Update payment record with onboarding_id
                if ($paymentMethod === 'mpesa') {
                    Mpesa::where('id', $paymentId)
                        ->update(['onboarding_id' => $onboarding->id]);
                } elseif ($paymentMethod === 'insurance') {
                    Insurance::where('id', $paymentId)
                        ->update(['onboarding_id' => $onboarding->id]);
                }

                return $onboarding;
            });

            Log::info('Onboarding created successfully:', ['onboarding_id' => $onboarding->id, 'user_id' => $user->id, 'role' => $user->role]);
            return response()->json([
                'success' => true,
                'message' => 'Onboarding created successfully',
                'data' => [
                    'onboarding' => $onboarding,
                    'payment_method' => $paymentMethod,
                    'payment_id' => $paymentId
                ]
            ], 201);
        } catch (\Exception $e) {
            Log::error('Onboarding creation error:', ['user_id' => $user?->id, 'role' => $user?->role, 'error' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);
            return response()->json([
                'success' => false,
                'message' => 'Failed to create onboarding',
                'error' => env('APP_DEBUG') ? $e->getMessage() : null
            ], 500);
        }
    }

    /**
     * Update an onboarding (admin, navigator, payer, user, claims).
     */
    public function update(Request $request, $id)
    {
        try {
            $user = $request->user();
            Log::debug('update debug:', [
                'user_exists' => !empty($user),
                'user_id' => $user?->id,
                'role' => $user?->role,
                'is_active' => $user?->isActive(),
                'onboarding_id' => $id
            ]);
            if (!$user) {
                Log::warning('No authenticated user found for onboarding update');
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized'
                ], 403);
            }
            if (!$user->isActive()) {
                Log::warning('Inactive user attempted to update onboarding:', ['user_id' => $user->id, 'role' => $user->role, 'onboarding_id' => $id]);
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized'
                ], 403);
            }
            $allowedRoles = [
                User::ROLE_ADMIN,
                User::ROLE_NAVIGATOR,
                User::ROLE_PAYER,
                User::ROLE_USER,
                User::ROLE_CLAIMS
            ];
            if (!in_array($user->role, $allowedRoles)) {
                Log::warning('Unauthorized role for updating onboarding:', ['user_id' => $user->id, 'role' => $user->role, 'onboarding_id' => $id]);
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized'
                ], 403);
            }

            $onboarding = Onboarding::findOrFail($id);

            // Restrict payers to their associated onboardings
            if ($user->role === User::ROLE_PAYER) {
                if (!$user->payer_id) {
                    Log::warning('Payer has no payer_id:', ['user_id' => $user->id, 'onboarding_id' => $id]);
                    return response()->json([
                        'success' => false,
                        'message' => 'Payer ID not set for this user'
                    ], 403);
                }
                if ($onboarding->payer_id !== $user->payer_id) {
                    Log::warning('Payer unauthorized to update onboarding:', ['user_id' => $user->id, 'onboarding_id' => $id]);
                    return response()->json([
                        'success' => false,
                        'message' => 'Unauthorized to update this onboarding'
                    ], 403);
                }
            }
            // Restrict users to their own onboarding
            elseif ($user->role === User::ROLE_USER && $onboarding->user_id !== $user->id) {
                Log::warning('User unauthorized to update onboarding:', ['user_id' => $user->id, 'onboarding_id' => $id]);
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized to update this onboarding'
                ], 403);
            }

            // Update onboarding in a transaction
            $onboarding = DB::transaction(function () use ($request, $onboarding, $user) {
                // Update onboarding fields
                $updateData = $request->only([
                    'first_name',
                    'middle_name',
                    'last_name',
                    'date_of_birth',
                    'emr_number',
                    'payer_id',
                    'clinic_id',
                    'diagnoses',
                    'age',
                    'sex',
                    'emergency_contact_name',
                    'emergency_contact_phone',
                    'emergency_contact_relation',
                    'weight_loss_target',
                    'hba1c_target',
                    'bp_target',
                    'activity_goal',
                    'hba1c_baseline',
                    'ldl_baseline',
                    'bp_baseline',
                    'weight_baseline',
                    'height',
                    'bmi_baseline',
                    'serum_creatinine_baseline',
                    'ecg_baseline',
                    'physical_activity_level',
                    'brief_medical_history',
                    'date_of_diagnosis',
                    'past_medical_interventions',
                    'relevant_family_history',
                    'has_weighing_scale',
                    'has_glucometer',
                    'has_bp_machine',
                    'has_tape_measure',
                    'dietary_restrictions',
                    'allergies_intolerances',
                    'lifestyle_factors',
                    'physical_limitations',
                    'psychosocial_factors',
                    'initial_consultation_date',
                    'follow_up_review1',
                    'follow_up_review2',
                    'additional_review',
                    'consent_date',
                    'consent_to_telehealth',
                    'consent_to_risks',
                    'consent_to_data_use',
                    'activation_code',
                    'is_active',
                    'hba1c_latest_reading_date',
                    'ldl_latest_reading_date',
                    'bp_latest_reading_date',
                    'weight_latest_reading_date',
                    'serum_creatinine_latest_reading_date',
                    'ecg_latest_reading_date',
                ]);

                $onboarding->update($updateData);

                return $onboarding;
            });

            Log::info('Onboarding updated successfully:', ['onboarding_id' => $onboarding->id, 'user_id' => $user->id, 'role' => $user->role]);
            return response()->json([
                'success' => true,
                'message' => 'Onboarding updated successfully',
                'data' => $onboarding
            ], 200);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            Log::error('Onboarding not found:', ['onboarding_id' => $id, 'user_id' => $user?->id, 'role' => $user?->role]);
            return response()->json([
                'success' => false,
                'message' => 'Onboarding not found'
            ], 404);
        } catch (\Exception $e) {
            Log::error('Onboarding update error:', ['onboarding_id' => $id, 'user_id' => $user?->id, 'role' => $user?->role, 'error' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);
            return response()->json([
                'success' => false,
                'message' => 'Failed to update onboarding',
                'error' => env('APP_DEBUG') ? $e->getMessage() : null
            ], 500);
        }
    }

    /**
     * Mark onboarding as complete (admin, navigator, payer, user, claims).
     */
    public function complete(Request $request, $id)
    {
        try {
            $user = $request->user();
            Log::debug('complete debug:', [
                'user_exists' => !empty($user),
                'user_id' => $user?->id,
                'role' => $user?->role,
                'is_active' => $user?->isActive(),
                'onboarding_id' => $id
            ]);
            if (!$user) {
                Log::warning('No authenticated user found for onboarding completion');
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized'
                ], 403);
            }
            if (!$user->isActive()) {
                Log::warning('Inactive user attempted to complete onboarding:', ['user_id' => $user->id, 'role' => $user->role, 'onboarding_id' => $id]);
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized'
                ], 403);
            }
            $allowedRoles = [
                User::ROLE_ADMIN,
                User::ROLE_NAVIGATOR,
                User::ROLE_PAYER,
                User::ROLE_USER,
                User::ROLE_CLAIMS
            ];
            if (!in_array($user->role, $allowedRoles)) {
                Log::warning('Unauthorized role for completing onboarding:', ['user_id' => $user->id, 'role' => $user->role, 'onboarding_id' => $id]);
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized'
                ], 403);
            }

            $onboarding = Onboarding::findOrFail($id);

            // Restrict payers to their associated onboardings
            if ($user->role === User::ROLE_PAYER) {
                if (!$user->payer_id) {
                    Log::warning('Payer has no payer_id:', ['user_id' => $user->id, 'onboarding_id' => $id]);
                    return response()->json([
                        'success' => false,
                        'message' => 'Payer ID not set for this user'
                    ], 403);
                }
                if ($onboarding->payer_id !== $user->payer_id) {
                    Log::warning('Payer unauthorized to complete onboarding:', ['user_id' => $user->id, 'onboarding_id' => $id]);
                    return response()->json([
                        'success' => false,
                        'message' => 'Unauthorized to complete this onboarding'
                    ], 403);
                }
            }
            // Restrict users to their own onboarding
            elseif ($user->role === User::ROLE_USER && $onboarding->user_id !== $user->id) {
                Log::warning('User unauthorized to complete onboarding:', ['user_id' => $user->id, 'onboarding_id' => $id]);
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized to complete this onboarding'
                ], 403);
            }

            if ($onboarding->payment_status === 'completed') {
                Log::warning('Onboarding already completed:', ['onboarding_id' => $id, 'user_id' => $user->id, 'role' => $user->role]);
                return response()->json([
                    'success' => false,
                    'message' => 'Onboarding is already completed'
                ], 400);
            }

            $onboarding->update([
                'payment_status' => 'completed',
                'is_active' => true
            ]);

            Log::info('Onboarding marked as complete:', ['onboarding_id' => $id, 'user_id' => $user->id, 'role' => $user->role]);
            return response()->json([
                'success' => true,
                'message' => 'Onboarding marked as complete',
                'onboarding' => $onboarding
            ], 200);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            Log::error('Onboarding not found:', ['onboarding_id' => $id, 'user_id' => $user?->id, 'role' => $user?->role]);
            return response()->json([
                'success' => false,
                'message' => 'Onboarding not found'
            ], 404);
        } catch (\Exception $e) {
            Log::error('Onboarding completion error:', ['onboarding_id' => $id, 'user_id' => $user?->id, 'role' => $user?->role, 'error' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);
            return response()->json([
                'success' => false,
                'message' => 'Failed to complete onboarding',
                'error' => env('APP_DEBUG') ? $e->getMessage() : null
            ], 500);
        }
    }

    /**
     * Delete an onboarding (admin, navigator, payer, user, claims).
     */
    public function destroy(Request $request, $id)
    {
        try {
            $user = $request->user();
            Log::debug('destroy debug:', [
                'user_exists' => !empty($user),
                'user_id' => $user?->id,
                'role' => $user?->role,
                'is_active' => $user?->isActive(),
                'onboarding_id' => $id
            ]);
            if (!$user) {
                Log::warning('No authenticated user found for onboarding deletion');
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized'
                ], 403);
            }
            if (!$user->isActive()) {
                Log::warning('Inactive user attempted to delete onboarding:', ['user_id' => $user->id, 'role' => $user->role, 'onboarding_id' => $id]);
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized'
                ], 403);
            }
            $allowedRoles = [
                User::ROLE_ADMIN,
                User::ROLE_NAVIGATOR,
                User::ROLE_PAYER,
                User::ROLE_USER,
                User::ROLE_CLAIMS
            ];
            if (!in_array($user->role, $allowedRoles)) {
                Log::warning('Unauthorized role for deleting onboarding:', ['user_id' => $user->id, 'role' => $user->role, 'onboarding_id' => $id]);
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized'
                ], 403);
            }

            $onboarding = Onboarding::findOrFail($id);

            // Restrict payers to their associated onboardings
            if ($user->role === User::ROLE_PAYER) {
                if (!$user->payer_id) {
                    Log::warning('Payer has no payer_id:', ['user_id' => $user->id, 'onboarding_id' => $id]);
                    return response()->json([
                        'success' => false,
                        'message' => 'Payer ID not set for this user'
                    ], 403);
                }
                if ($onboarding->payer_id !== $user->payer_id) {
                    Log::warning('Payer unauthorized to delete onboarding:', ['user_id' => $user->id, 'onboarding_id' => $id]);
                    return response()->json([
                        'success' => false,
                        'message' => 'Unauthorized to delete this onboarding'
                    ], 403);
                }
            }
            // Restrict users to their own onboarding
            elseif ($user->role === User::ROLE_USER && $onboarding->user_id !== $user->id) {
                Log::warning('User unauthorized to delete onboarding:', ['user_id' => $user->id, 'onboarding_id' => $id]);
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized to delete this onboarding'
                ], 403);
            }

            // Delete onboarding in a transaction
            DB::transaction(function () use ($onboarding, $user, $id) {
                // Update associated payment records to remove onboarding_id
                if ($onboarding->payment_method === 'mpesa') {
                    Mpesa::where('onboarding_id', $onboarding->id)
                        ->update(['onboarding_id' => null]);
                } elseif ($onboarding->payment_method === 'insurance') {
                    Insurance::where('onboarding_id', $onboarding->id)
                        ->update(['onboarding_id' => null]);
                }

                // Delete the onboarding record
                $onboarding->delete();
            });

            Log::info('Onboarding deleted successfully:', ['onboarding_id' => $id, 'user_id' => $user->id, 'role' => $user->role]);
            return response()->json([
                'success' => true,
                'message' => 'Onboarding deleted successfully'
            ], 200);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            Log::error('Onboarding not found:', ['onboarding_id' => $id, 'user_id' => $user?->id, 'role' => $user?->role]);
            return response()->json([
                'success' => false,
                'message' => 'Onboarding not found'
            ], 404);
        } catch (\Exception $e) {
            Log::error('Onboarding deletion error:', ['onboarding_id' => $id, 'user_id' => $user?->id, 'role' => $user?->role, 'error' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete onboarding',
                'error' => env('APP_DEBUG') ? $e->getMessage() : null
            ], 500);
        }
    }

    /**
     * Get onboarding for the authenticated user (admin, navigator, payer, user, claims).
     */
    public function getUserOnboarding(Request $request)
    {
        try {
            $user = $request->user();
            Log::debug('getUserOnboarding debug:', [
                'user_exists' => !empty($user),
                'user_id' => $user?->id,
                'role' => $user?->role,
                'is_active' => $user?->isActive(),
            ]);
            if (!$user) {
                Log::warning('No authenticated user found for fetching user onboarding');
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized'
                ], 403);
            }
            if (!$user->isActive()) {
                Log::warning('Inactive user attempted to fetch user onboarding:', ['user_id' => $user->id, 'role' => $user->role]);
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized'
                ], 403);
            }
            $allowedRoles = [
                User::ROLE_ADMIN,
                User::ROLE_NAVIGATOR,
                User::ROLE_PAYER,
                User::ROLE_USER,
                User::ROLE_CLAIMS
            ];
            if (!in_array($user->role, $allowedRoles)) {
                Log::warning('Unauthorized role for fetching user onboarding:', ['user_id' => $user->id, 'role' => $user->role]);
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized'
                ], 403);
            }

            $query = Onboarding::query();

            // Restrict to user's own onboarding or associated records
            if ($user->role === User::ROLE_USER) {
                $query->where('user_id', $user->id);
                Log::debug('Querying onboarding for ROLE_USER:', ['user_id' => $user->id]);
            } elseif ($user->role === User::ROLE_PAYER) {
                if (!$user->payer_id) {
                    Log::warning('Payer has no payer_id:', ['user_id' => $user->id]);
                    return response()->json([
                        'success' => false,
                        'message' => 'Payer ID not set for this user'
                    ], 403);
                }
                $query->where('payer_id', $user->payer_id);
                Log::debug('Querying onboarding for ROLE_PAYER:', ['user_id' => $user->id, 'payer_id' => $user->payer_id]);
            } else {
                $query->where('user_id', $user->id);
                Log::debug('Querying onboarding for other roles:', ['user_id' => $user->id, 'role' => $user->role]);
            }

            $onboarding = $query->first();
            Log::debug('Onboarding query result:', [
                'user_id' => $user->id,
                'role' => $user->role,
                'onboarding_exists' => !empty($onboarding),
                'onboarding_id' => $onboarding?->id
            ]);

            if (!$onboarding) {
                Log::info('No onboarding record found:', ['user_id' => $user->id, 'role' => $user->role]);
                return response()->json([
                    'success' => false,
                    'message' => 'No onboarding record found'
                ], 404);
            }

            Log::info('Onboarding retrieved successfully:', ['user_id' => $user->id, 'role' => $user->role, 'onboarding_id' => $onboarding->id]);
            return response()->json([
                'success' => true,
                'message' => 'Onboarding retrieved successfully',
                'onboarding' => $onboarding
            ], 200);
        } catch (\Exception $e) {
            Log::error('Error fetching user onboarding:', ['user_id' => $request->user()?->id, 'role' => $request->user()?->role, 'error' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch onboarding',
                'error' => env('APP_DEBUG') ? $e->getMessage() : null
            ], 500);
        }
    }

    /**
     * Update the authenticated user's onboarding data (user only).
     */
    public function updateUserOnboarding(Request $request)
    {
        try {
            $user = $request->user();
            Log::debug('updateUserOnboarding debug:', [
                'user_exists' => !empty($user),
                'user_id' => $user?->id,
                'role' => $user?->role,
                'is_active' => $user?->isActive(),
            ]);
            if (!$user) {
                Log::warning('No authenticated user found for updating user onboarding');
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized'
                ], 403);
            }
            if (!$user->isActive()) {
                Log::warning('Inactive user attempted to update onboarding:', ['user_id' => $user->id, 'role' => $user->role]);
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized'
                ], 403);
            }
            if ($user->role !== User::ROLE_USER) {
                Log::warning('Unauthorized role for updating user onboarding:', ['user_id' => $user->id, 'role' => $user->role]);
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized to update user onboarding'
                ], 403);
            }

            $onboarding = Onboarding::where('user_id', $user->id)->first();
            if (!$onboarding) {
                Log::info('No onboarding record found for user:', ['user_id' => $user->id, 'role' => $user->role]);
                return response()->json([
                    'success' => false,
                    'message' => 'No onboarding record found'
                ], 404);
            }

            // Validate the request
            $validator = Validator::make($request->all(), [
                'first_name' => 'required|string|max:50',
                'middle_name' => 'nullable|string|max:50',
                'last_name' => 'required|string|max:50',
                'date_of_birth' => 'required|date',
                'clinic_id' => 'required|exists:clinics,id',
                'age' => 'required|integer|min:0',
                'sex' => 'required|in:male,female,other',
                'emergency_contact_name' => 'required|string|max:100',
                'emergency_contact_phone' => 'required|string|max:20|regex:/^\+?\d{10,15}$/',
                'emergency_contact_relation' => 'required|string|max:100',
                'weight_loss_target' => 'nullable|numeric|min:0',
                'hba1c_target' => 'nullable|numeric|min:0',
                'bp_target' => 'nullable|string|regex:/^\d{2,3}\/\d{2,3}$/',
                'activity_goal' => 'nullable|string|max:100',
                'hba1c_baseline' => 'nullable|numeric|min:0',
                'ldl_baseline' => 'nullable|numeric|min:0',
                'bp_baseline' => 'nullable|string|regex:/^\d{2,3}\/\d{2,3}$/',
                'weight_baseline' => 'nullable|numeric|min:0',
                'height' => 'nullable|numeric|min:0',
                'bmi_baseline' => 'nullable|numeric|min:0',
                'serum_creatinine_baseline' => 'nullable|numeric|min:0',
                'ecg_baseline' => 'nullable|string|max:100',
                'physical_activity_level' => 'nullable|in:sedentary,lightly_active,moderately_active,very_active',
                'diagnoses' => 'nullable|array',
                'diagnoses.*' => 'string|max:255',
                'brief_medical_history' => 'nullable|string|max:1000',
                'date_of_diagnosis' => 'nullable|date',
                'past_medical_interventions' => 'nullable|string|max:1000',
                'relevant_family_history' => 'nullable|string|max:1000',
                'has_weighing_scale' => 'boolean',
                'has_glucometer' => 'boolean',
                'has_bp_machine' => 'boolean',
                'has_tape_measure' => 'boolean',
                'dietary_restrictions' => 'nullable|string|max:1000',
                'allergies_intolerances' => 'nullable|string|max:1000',
                'lifestyle_factors' => 'nullable|string|max:1000',
                'physical_limitations' => 'nullable|string|max:1000',
                'psychosocial_factors' => 'nullable|string|max:1000',
                'initial_consultation_date' => 'nullable|date',
                'follow_up_review1' => 'nullable|date',
                'follow_up_review2' => 'nullable|date',
                'additional_review' => 'nullable|date',
                'consent_date' => 'nullable|date',
                'consent_to_telehealth' => 'boolean',
                'consent_to_risks' => 'boolean',
                'consent_to_data_use' => 'boolean',
                'activation_code' => 'nullable|string|max:255',
                'hba1c_latest_reading_date' => 'nullable|date',
                'ldl_latest_reading_date' => 'nullable|date',
                'bp_latest_reading_date' => 'nullable|date',
                'weight_latest_reading_date' => 'nullable|date',
                'serum_creatinine_latest_reading_date' => 'nullable|date',
                'ecg_latest_reading_date' => 'nullable|date',
            ]);

            if ($validator->fails()) {
                Log::warning('Validation failed for updating user onboarding:', [
                    'user_id' => $user->id,
                    'role' => $user->role,
                    'errors' => $validator->errors()->all()
                ]);
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            // Update onboarding in a transaction
            $onboarding = DB::transaction(function () use ($request, $onboarding, $user) {
                $updateData = $request->only([
                    'first_name',
                    'middle_name',
                    'last_name',
                    'date_of_birth',
                    'clinic_id',
                    'age',
                    'sex',
                    'emergency_contact_name',
                    'emergency_contact_phone',
                    'emergency_contact_relation',
                    'weight_loss_target',
                    'hba1c_target',
                    'bp_target',
                    'activity_goal',
                    'hba1c_baseline',
                    'ldl_baseline',
                    'bp_baseline',
                    'weight_baseline',
                    'height',
                    'bmi_baseline',
                    'serum_creatinine_baseline',
                    'ecg_baseline',
                    'physical_activity_level',
                    'diagnoses',
                    'brief_medical_history',
                    'date_of_diagnosis',
                    'past_medical_interventions',
                    'relevant_family_history',
                    'has_weighing_scale',
                    'has_glucometer',
                    'has_bp_machine',
                    'has_tape_measure',
                    'dietary_restrictions',
                    'allergies_intolerances',
                    'lifestyle_factors',
                    'physical_limitations',
                    'psychosocial_factors',
                    'initial_consultation_date',
                    'follow_up_review1',
                    'follow_up_review2',
                    'additional_review',
                    'consent_date',
                    'consent_to_telehealth',
                    'consent_to_risks',
                    'consent_to_data_use',
                    'activation_code',
                    'hba1c_latest_reading_date',
                    'ldl_latest_reading_date',
                    'bp_latest_reading_date',
                    'weight_latest_reading_date',
                    'serum_creatinine_latest_reading_date',
                    'ecg_latest_reading_date',
                ]);

                $onboarding->update($updateData);
                return $onboarding;
            });

            Log::info('User onboarding updated successfully:', [
                'onboarding_id' => $onboarding->id,
                'user_id' => $user->id,
                'role' => $user->role
            ]);
            return response()->json([
                'success' => true,
                'message' => 'Onboarding updated successfully',
                'data' => $onboarding
            ], 200);
        } catch (\Exception $e) {
            Log::error('Error updating user onboarding:', [
                'user_id' => $request->user()?->id,
                'role' => $request->user()?->role,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return response()->json([
                'success' => false,
                'message' => 'Failed to update onboarding',
                'error' => env('APP_DEBUG') ? $e->getMessage() : null
            ], 500);
        }
    }
}