<?php

namespace App\Http\Controllers;

use App\Models\Onboarding;
use App\Models\MpesaPayment;
use App\Models\Insurance;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class AdminOnboardingController extends Controller
{   
    /**
     * Get all onboardings (admin only).
     */
    public function index(Request $request)
    {
        try {
            if (!$request->user()->isAdmin()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized'
                ], 403);
            }

            $onboardings = Onboarding::all();
            return response()->json([
                'success' => true,
                'message' => 'Onboardings retrieved successfully',
                'onboardings' => $onboardings
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch onboardings',
                'error' => env('APP_DEBUG') ? $e->getMessage() : null
            ], 500);
        }
    }

    /**
     * Get a specific onboarding (admin only).
     */
    public function show(Request $request, $id)
    {
        try {
            if (!$request->user()->isAdmin()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized'
                ], 403);
            }

            $onboarding = Onboarding::findOrFail($id);
            return response()->json([
                'success' => true,
                'message' => 'Onboarding retrieved successfully',
                'onboarding' => $onboarding
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch onboarding',
                'error' => env('APP_DEBUG') ? $e->getMessage() : null
            ], 500);
        }
    }

    /**
     * Create a new onboarding (admin only).
     */
    public function store(Request $request)
    {
        Log::info('Admin onboarding request received:', $request->all());

        $validator = Validator::make($request->all(), [
            'first_name' => 'required|string|max:50',
            'last_name' => 'required|string|max:50',
            'date_of_birth' => 'required|date',
            'emr_number' => 'nullable|string|max:50',
            'payer_id' => 'nullable|string|max:255',
            'clinic_id' => 'required|exists:clinics,id',
            'diagnoses' => 'nullable|array',
            'diagnoses.*' => 'string|max:255',
            'medications' => 'nullable|array',
            'medications.*' => 'string|max:255',
            'age' => \Carbon\Carbon::parse($request->date_of_birth)->age, // Calculate age from DOB
            'sex' => 'required|in:male,female,other',
            'emergency_contact_name' => 'nullable|string|max:100',
            'emergency_contact_phone' => 'nullable|string|max:15',
            'emergency_contact_relation' => 'nullable|string|max:100',
            'weight_loss_target' => 'nullable|numeric|min:0',
            'hba1c_target' => 'nullable|numeric|min:0',
            'bp_target' => 'nullable|string|max:10',
            'activity_goal' => 'nullable|string|max:100',
            'hba1c_baseline' => 'nullable|numeric|min:0',
            'ldl_baseline' => 'nullable|numeric|min:0',
            'bp_baseline' => 'nullable|string|max:10',
            'weight_baseline' => 'nullable|numeric|min:0',
            'height' => 'nullable|numeric|min:0',
            'bmi_baseline' => 'nullable|numeric|min:0',
            'serum_creatinine_baseline' => 'nullable|numeric|min:0',
            'ecg_baseline' => 'nullable|string|max:100',
            'physical_activity_level' => 'nullable|in:sedentary,lightly_active,moderately_active,very_active',
            'brief_medical_history' => 'nullable|string|max:1000',
            'years_since_diagnosis' => 'nullable|integer|min:0',
            'past_medical_interventions' => 'nullable|string|max:1000',
            'relevant_family_history' => 'nullable|string|max:1000',
            'has_weighing_scale' => 'sometimes|boolean',
            'has_glucometer' => 'sometimes|boolean',
            'has_bp_machine' => 'sometimes|boolean',
            'has_tape_measure' => 'sometimes|boolean',
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
            'consent_to_telehealth' => 'sometimes|boolean',
            'consent_to_risks' => 'sometimes|boolean',
            'consent_to_data_use' => 'sometimes|boolean',
            'payment_method' => 'required|in:mpesa,insurance',
            'payment_id' => 'required|integer',
            'payment_status' => 'nullable|in:pending,completed,failed',
            'mpesa_number' => 'nullable|string|max:15',
            'mpesa_reference' => 'nullable|string|max:50',
            'insurance_provider' => 'nullable|string|max:255',
            'insurance_id' => 'nullable|max:255',
            'policy_number' => 'nullable|string|max:255',
            'activation_code' => 'nullable|string|max:50',
            'is_active' => 'sometimes|boolean'
        ]);

        if ($validator->fails()) {
            Log::error('Validation failed:', $validator->errors()->toArray());
            return response()->json([
                'success' => false,
                'message' => 'Validation errors',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            if (!$request->user()->isAdmin()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized'
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
                    return response()->json([
                        'success' => false,
                        'message' => 'M-Pesa payment not completed or not found'
                    ], 400);
                }
                $paymentVerified = true;
            } elseif ($paymentMethod === 'insurance') {
                $payment = Insurance::where('id', $paymentId)->first();
                if (!$payment) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Insurance record not found'
                    ], 400);
                }
                $paymentVerified = true;
            }

            if (!$paymentVerified) {
                return response()->json([
                    'success' => false,
                    'message' => 'Payment verification failed'
                ], 400);
            }

            // Create onboarding and update payment record in a transaction
            $onboarding = DB::transaction(function () use ($request, $paymentMethod, $paymentId) {
                $onboarding = Onboarding::create([
                    'user_id' => request()->user()->id, // Prepopulate with authenticated admin's ID
                    'first_name' => $request->first_name,
                    'last_name' => $request->last_name,
                    'date_of_birth' => $request->date_of_birth,
                    'emr_number' => $request->emr_number,
                    'payer_id' => $request->payer_id,
                    'clinic_id' => $request->clinic_id,
                    'diagnoses' => $request->diagnoses,
                    'medications' => $request->medications,
                    'age' => $request->age,
                    'sex' => $request->sex,
                    'date_of_onboarding' => now()->toDateString(), // Prepopulate with current date
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
                    'years_since_diagnosis' => $request->years_since_diagnosis,
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
                    'payment_status' => $request->payment_status,
                    'mpesa_number' => $request->mpesa_number,
                    'mpesa_reference' => $request->mpesa_reference,
                    'insurance_provider' => $request->insurance_provider,
                    'insurance_id' => $request->insurance_id,
                    'policy_number' => $request->policy_number,
                    'activation_code' => $request->activation_code,
                    'is_active' => $request->is_active ?? true
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

            Log::info('Onboarding successful:', ['onboarding_id' => $onboarding->id]);

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
            Log::error('Onboarding error:', ['error' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);
            return response()->json([
                'success' => false,
                'message' => 'Failed to create onboarding',
                'error' => env('APP_DEBUG') ? $e->getMessage() : null
            ], 500);
        }
    }

    /**
     * Update an onboarding (admin only).
     */
  public function update(Request $request, $id)
{
    try {
        if (!$request->user()->isAdmin()) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized'
            ], 403);
        }

        $onboarding = Onboarding::findOrFail($id);

        // Only update fields that are present in the request
        $updateData = $request->only([
            'first_name',
            'last_name',
            'date_of_birth',
            'emr_number',
            'clinic_id',
            'diagnoses',
            'medications',
            'age',
            'sex',
            'emergency_contact_name',
            'emergency_contact_phone',
            'emergency_contact_relation',
            'brief_medical_history',
            'years_since_diagnosis',
            'past_medical_interventions',
            'relevant_family_history',
            'hba1c_baseline',
            'ldl_baseline',
            'bp_baseline',
            'weight_baseline',
            'height',
            'bmi_baseline',
            'serum_creatinine_baseline',
            'ecg_baseline',
            'physical_activity_level',
            'weight_loss_target',
            'hba1c_target',
            'bp_target',
            'activity_goal',
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
            'is_active'
        ]);

        $onboarding->update($updateData);

        return response()->json([
            'success' => true,
            'message' => 'Onboarding updated successfully',
            'data' => $onboarding
        ], 200);
    } catch (\Exception $e) {
        Log::error('Onboarding update error:', ['error' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);
        return response()->json([
            'success' => false,
            'message' => 'Failed to update onboarding',
            'error' => env('APP_DEBUG') ? $e->getMessage() : null
        ], 500);
    }
}

    /**
     * Mark onboarding as complete (admin only).
     */
    public function complete(Request $request, $id)
    {
        try {
            if (!$request->user()->isAdmin()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized'
                ], 403);
            }

            $onboarding = Onboarding::findOrFail($id);

            if ($onboarding->payment_status === 'completed') {
                return response()->json([
                    'success' => false,
                    'message' => 'Onboarding is already completed'
                ], 400);
            }

            $onboarding->update([
                'payment_status' => 'completed',
                'is_active' => true
            ]);
            return response()->json([
                'success' => true,
                'message' => 'Onboarding marked as complete',
                'onboarding' => $onboarding
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to complete onboarding',
                'error' => env('APP_DEBUG') ? $e->getMessage() : null
            ], 500);
        }

        
    }
    public function destroy(Request $request, $id)
{
    try {
        if (!$request->user()->isAdmin()) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized'
            ], 403);
        }

        $onboarding = Onboarding::findOrFail($id);
        
        // Optional: Check for related records before deletion
        // if ($onboarding->payments()->exists()) {
        //     return response()->json([
        //         'success' => false,
        //         'message' => 'Cannot delete onboarding with associated payments'
        //     ], 400);
        // }

        $onboarding->delete();

        return response()->json([
            'success' => true,
            'message' => 'Onboarding deleted successfully'
        ], 200);
    } catch (\Exception $e) {
        Log::error('Onboarding deletion error:', [
            'error' => $e->getMessage(),
            'onboarding_id' => $id
        ]);
        return response()->json([
            'success' => false,
            'message' => 'Failed to delete onboarding',
            'error' => env('APP_DEBUG') ? $e->getMessage() : null
        ], 500);
    }
}

public function getUserOnboarding(Request $request)
{
    try {
        $user = $request->user();
        
        $onboarding = Onboarding::where('user_id', $user->id)->first();
        
        if (!$onboarding) {
            return response()->json([
                'success' => false,
                'message' => 'No onboarding record found for this user'
            ], 404);
        }

        return response()->json([
            'success' => true,
            'message' => 'Onboarding retrieved successfully',
            'onboarding' => $onboarding
        ], 200);
    } catch (\Exception $e) {
        return response()->json([
            'success' => false,
            'message' => 'Failed to fetch onboarding',
            'error' => env('APP_DEBUG') ? $e->getMessage() : null
        ], 500);
    }
}
}
