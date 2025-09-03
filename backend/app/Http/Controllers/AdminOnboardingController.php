<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\Onboarding;
use App\Models\MpesaPayment;
use App\Models\Insurance;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class AdminOnboardingController extends Controller
{
    /**
     * Check if the user has access to onboarding records.
     */
    private function hasAccess(Request $request)
    {
        $user = $request->user();
        if (!$user) {
            Log::warning('No authenticated user found for onboarding access');
            return false;
        }
        if (!$user->isActive()) {
            Log::warning('Inactive user attempted to access onboarding:', ['user_id' => $user->id, 'role' => $user->role]);
            return false;
        }
        // Allow all roles: admin, navigator, payer, user, claims
        $allowedRoles = ['admin', User::ROLE_NAVIGATOR, User::ROLE_PAYER, User::ROLE_USER, User::ROLE_CLAIMS];
        $hasAccess = in_array($user->role, $allowedRoles);
        Log::debug('Checking access for user:', ['user_id' => $user->id, 'role' => $user->role, 'has_access' => $hasAccess]);
        return $hasAccess;
    }

    /**
     * Get all onboardings (admin, navigator, payer, user).
     */
    public function index(Request $request)
    {
        try {
            if (!$this->hasAccess($request)) {
                Log::warning('Unauthorized attempt to fetch onboardings:', ['user_id' => $request->user()?->id, 'role' => $request->user()?->role]);
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized'
                ], 403);
            }

            $user = $request->user();
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
     * Get a specific onboarding (admin, navigator, payer, user).
     */
    public function show(Request $request, $id)
    {
        try {
            if (!$this->hasAccess($request)) {
                Log::warning('Unauthorized attempt to fetch onboarding:', ['user_id' => $request->user()?->id, 'role' => $request->user()?->role, 'onboarding_id' => $id]);
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized'
                ], 403);
            }

            $user = $request->user();
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
     * Create a new onboarding (admin, navigator, payer, user).
     */
    public function store(Request $request)
    {
        Log::info('Onboarding creation request received:', ['user_id' => $request->user()?->id, 'role' => $request->user()?->role, 'data' => $request->all()]);

        try {
            if (!$this->hasAccess($request)) {
                Log::warning('Unauthorized attempt to create onboarding:', ['user_id' => $request->user()?->id, 'role' => $request->user()?->role]);
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized'
                ], 403);
            }

            $user = $request->user();
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
                $payment = MpesaPayment::where('id', $paymentId)
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
                    MpesaPayment::where('id', $paymentId)
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
     * Update an onboarding (admin, navigator, payer, user).
     */
    public function update(Request $request, $id)
    {
        try {
            if (!$this->hasAccess($request)) {
                Log::warning('Unauthorized attempt to update onboarding:', ['user_id' => $request->user()?->id, 'role' => $request->user()?->role, 'onboarding_id' => $id]);
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized'
                ], 403);
            }

            $user = $request->user();
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
     * Mark onboarding as complete (admin, navigator, payer, user).
     */
    public function complete(Request $request, $id)
    {
        try {
            if (!$this->hasAccess($request)) {
                Log::warning('Unauthorized attempt to complete onboarding:', ['user_id' => $request->user()?->id, 'role' => $request->user()?->role, 'onboarding_id' => $id]);
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized'
                ], 403);
            }

            $user = $request->user();
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
     * Delete an onboarding (admin, navigator, payer, user).
     */
    public function destroy(Request $request, $id)
    {
        try {
            if (!$this->hasAccess($request)) {
                Log::warning('Unauthorized attempt to delete onboarding:', ['user_id' => $request->user()?->id, 'role' => $request->user()?->role, 'onboarding_id' => $id]);
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized'
                ], 403);
            }

            $user = $request->user();
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
                    MpesaPayment::where('onboarding_id', $onboarding->id)
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
     * Get onboarding for the authenticated user (admin, navigator, payer, user).
     */
    public function getUserOnboarding(Request $request)
    {
        try {
            if (!$this->hasAccess($request)) {
                Log::warning('Unauthorized attempt to fetch user onboarding:', ['user_id' => $request->user()?->id, 'role' => $request->user()?->role]);
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized'
                ], 403);
            }

            $user = $request->user();
            $query = Onboarding::query();

            // Restrict to user's own onboarding or associated records
            if ($user->role === User::ROLE_USER) {
                $query->where('user_id', $user->id);
            } elseif ($user->role === User::ROLE_PAYER) {
                if (!$user->payer_id) {
                    Log::warning('Payer has no payer_id:', ['user_id' => $user->id]);
                    return response()->json([
                        'success' => false,
                        'message' => 'Payer ID not set for this user'
                    ], 403);
                }
                $query->where('payer_id', $user->payer_id);
            }
            // Admins and navigators can see any onboarding, so no additional restriction

            $onboarding = $query->first();

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
}