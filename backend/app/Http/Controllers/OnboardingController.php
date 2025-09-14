<?php

namespace App\Http\Controllers;

use App\Models\Onboarding;
use App\Models\Insurance;
use App\Models\Mpesa;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class OnboardingController extends Controller
{
    public function store(Request $request)
    {
        $today = now()->format('Y-m-d');
        $validator = Validator::make($request->all(), [
            'user_id' => 'required|integer|exists:users,id|unique:onboardings,user_id',
            'first_name' => 'required|string|max:255',
            'middle_name' => 'nullable|string|max:255',
            'last_name' => 'required|string|max:255',
            'date_of_birth' => 'required|date|before_or_equal:' . $today,
            'emr_number' => 'nullable|string|max:255',
            'payer_id' => 'nullable|integer|exists:payers,id',
            'clinic_id' => 'required|integer|exists:clinics,id',
            'diagnoses' => 'nullable|json',
            'date_of_diagnosis' => 'nullable|date',
            'medications' => 'nullable|json',
            'age' => 'required|integer|min:0',
            'sex' => 'required|in:male,female,other',
            'date_of_onboarding' => 'required|date',
            'emergency_contact_name' => 'required|string|max:255',
            'emergency_contact_phone' => 'required|string|regex:/^0[0-9]{9}$/|size:10',
            'emergency_contact_relation' => 'required|string|max:255',
            'brief_medical_history' => 'nullable|string',
            'years_since_diagnosis' => 'nullable|integer|min:0',
            'past_medical_interventions' => 'nullable|string',
            'relevant_family_history' => 'nullable|string',
            'hba1c_baseline' => 'nullable|numeric|min:0',
            'ldl_baseline' => 'nullable|numeric|min:0',
            'bp_baseline' => 'nullable|string|max:255',
            'weight_baseline' => 'nullable|numeric|min:0',
            'height' => 'nullable|numeric|min:0',
            'bmi_baseline' => 'nullable|numeric|min:0',
            'serum_creatinine_baseline' => 'nullable|numeric|min:0',
            'ecg_baseline' => 'nullable|string|max:255',
            'physical_activity_level' => 'nullable|string|max:255',
            'weight_loss_target' => 'nullable|numeric|min:0',
            'hba1c_target' => 'nullable|numeric|min:0',
            'bp_target' => 'nullable|string|max:255',
            'activity_goal' => 'nullable|string|max:255',
            'has_weighing_scale' => 'nullable|boolean',
            'has_glucometer' => 'nullable|boolean',
            'has_bp_machine' => 'nullable|boolean',
            'has_tape_measure' => 'nullable|boolean',
            'dietary_restrictions' => 'nullable|string',
            'allergies_intolerances' => 'nullable|string',
            'lifestyle_factors' => 'nullable|string',
            'physical_limitations' => 'nullable|string',
            'psychosocial_factors' => 'nullable|string',
            'initial_consultation_date' => 'nullable|date',
            'follow_up_review1' => 'nullable|date',
            'follow_up_review2' => 'nullable|date',
            'additional_review' => 'nullable|date',
            'consent_date' => 'required|date',
            'activation_code' => 'nullable|string|max:255',
            'is_active' => 'nullable|boolean',
            'consent_to_telehealth' => 'required|boolean',
            'consent_to_risks' => 'required|boolean',
            'consent_to_data_use' => 'required|boolean',
            'payment_method' => 'required|in:mpesa,insurance',
            'payment_status' => 'required|in:pending,completed',
            'mpesa_number' => 'nullable|string|regex:/^0[0-9]{9}$/|size:10',
            'mpesa_reference' => 'nullable|string|max:255',
            'mpesa_client_name' => 'nullable|string|max:255',
            'mpesa_amount' => 'nullable|numeric|min:0',
            'mpesa_transaction_type' => 'nullable|string|max:255',
            'mpesa_status' => 'nullable|in:pending,completed,failed',
            'mpesa_confirmation_code' => 'nullable|string|max:255',
            'policy_number' => 'nullable|string|max:255',
            'insurance_id' => 'nullable|integer|exists:insurance,id',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $validated = $validator->validated();

        // Conditional validation for payment method
        if ($validated['payment_method'] === 'mpesa') {
            $mpesaValidator = Validator::make($request->all(), [
                'mpesa_reference' => 'required|string|max:255',
                'mpesa_client_name' => 'required|string|max:255',
                'mpesa_number' => 'required|string|regex:/^0[0-9]{9}$/|size:10',
                'mpesa_amount' => 'required|numeric|min:0',
                'mpesa_transaction_type' => 'required|string|max:255',
                'mpesa_status' => 'required|in:pending,completed,failed',
                'mpesa_confirmation_code' => 'required|string|max:255',
            ]);
            if ($mpesaValidator->fails()) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'M-Pesa validation failed',
                    'errors' => $mpesaValidator->errors(),
                ], 422);
            }
           
            $validated['policy_number'] = null;
            $validated['insurance_id'] = null;
        } elseif ($validated['payment_method'] === 'insurance') {
            $insuranceValidator = Validator::make($request->all(), [
                
                'policy_number' => 'required|string|max:255',
                'insurance_id' => 'required|integer|exists:insurance,id',
            ]);
            if ($insuranceValidator->fails()) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Insurance validation failed',
                    'errors' => $insuranceValidator->errors(),
                ], 422);
            }
            $validated['mpesa_reference'] = null;
            $validated['mpesa_client_name'] = null;
            $validated['mpesa_number'] = null;
            $validated['mpesa_amount'] = null;
            $validated['mpesa_transaction_type'] = null;
            $validated['mpesa_status'] = null;
            $validated['mpesa_confirmation_code'] = null;
        }

        DB::beginTransaction();

        try {
            // Create onboarding record
            $onboardingData = [
                'user_id' => $validated['user_id'],
                'first_name' => $validated['first_name'],
                'middle_name' => $validated['middle_name'],
                'last_name' => $validated['last_name'],
                'date_of_birth' => $validated['date_of_birth'],
                'emr_number' => $validated['emr_number'] ?? null,
                'payer_id' => $validated['payer_id'] ?? null,
                'clinic_id' => $validated['clinic_id'],
                'diagnoses' => $validated['diagnoses'] ?? null,
                'date_of_diagnosis' => $validated['date_of_diagnosis'] ?? null,
                'medications' => $validated['medications'] ?? null,
                'age' => $validated['age'],
                'sex' => $validated['sex'],
                'date_of_onboarding' => $validated['date_of_onboarding'],
                'emergency_contact_name' => $validated['emergency_contact_name'],
                'emergency_contact_phone' => $validated['emergency_contact_phone'],
                'emergency_contact_relation' => $validated['emergency_contact_relation'],
                'brief_medical_history' => $validated['brief_medical_history'] ?? null,
                'years_since_diagnosis' => $validated['years_since_diagnosis'] ?? null,
                'past_medical_interventions' => $validated['past_medical_interventions'] ?? null,
                'relevant_family_history' => $validated['relevant_family_history'] ?? null,
                'hba1c_baseline' => $validated['hba1c_baseline'] ?? null,
                'ldl_baseline' => $validated['ldl_baseline'] ?? null,
                'bp_baseline' => $validated['bp_baseline'] ?? null,
                'weight_baseline' => $validated['weight_baseline'] ?? null,
                'height' => $validated['height'] ?? null,
                'bmi_baseline' => $validated['bmi_baseline'] ?? null,
                'serum_creatinine_baseline' => $validated['serum_creatinine_baseline'] ?? null,
                'ecg_baseline' => $validated['ecg_baseline'] ?? null,
                'physical_activity_level' => $validated['physical_activity_level'] ?? null,
                'weight_loss_target' => $validated['weight_loss_target'] ?? null,
                'hba1c_target' => $validated['hba1c_target'] ?? null,
                'bp_target' => $validated['bp_target'] ?? null,
                'activity_goal' => $validated['activity_goal'] ?? null,
                'has_weighing_scale' => $validated['has_weighing_scale'] ?? false,
                'has_glucometer' => $validated['has_glucometer'] ?? false,
                'has_bp_machine' => $validated['has_bp_machine'] ?? false,
                'has_tape_measure' => $validated['has_tape_measure'] ?? false,
                'dietary_restrictions' => $validated['dietary_restrictions'] ?? null,
                'allergies_intolerances' => $validated['allergies_intolerances'] ?? null,
                'lifestyle_factors' => $validated['lifestyle_factors'] ?? null,
                'physical_limitations' => $validated['physical_limitations'] ?? null,
                'psychosocial_factors' => $validated['psychosocial_factors'] ?? null,
                'initial_consultation_date' => $validated['initial_consultation_date'] ?? null,
                'follow_up_review1' => $validated['follow_up_review1'] ?? null,
                'follow_up_review2' => $validated['follow_up_review2'] ?? null,
                'additional_review' => $validated['additional_review'] ?? null,
                'consent_date' => $validated['consent_date'],
                'activation_code' => $validated['activation_code'] ?? null,
                'is_active' => $validated['is_active'] ?? false,
                'consent_to_telehealth' => $validated['consent_to_telehealth'],
                'consent_to_risks' => $validated['consent_to_risks'],
                'consent_to_data_use' => $validated['consent_to_data_use'],
                'payment_method' => $validated['payment_method'],
                'payment_status' => $validated['payment_status'],
                'mpesa_number' => $validated['mpesa_number'] ?? null,
                'mpesa_reference' => $validated['mpesa_reference'] ?? null,
                'mpesa_client_name' => $validated['mpesa_client_name'] ?? null,
                'mpesa_amount' => $validated['mpesa_amount'] ?? null,
                'mpesa_transaction_type' => $validated['mpesa_transaction_type'] ?? null,
                'mpesa_status' => $validated['mpesa_status'] ?? null,
                'mpesa_confirmation_code' => $validated['mpesa_confirmation_code'] ?? null,
               
                'policy_number' => $validated['policy_number'] ?? null,
                'insurance_id' => $validated['insurance_id'] ?? null,
            ];

            $onboarding = Onboarding::create($onboardingData);

            // Create mpesa record if payment_method is mpesa
            if ($validated['payment_method'] === 'mpesa') {
                $mpesa = Mpesa::create([
                    'user_id' => $validated['user_id'],
                    'onboarding_id' => $onboarding->id,
                    'mpesa_reference' => $validated['mpesa_reference'],
                    'client_name' => $validated['mpesa_client_name'],
                    'phone_number' => $validated['mpesa_number'],
                    'amount' => $validated['mpesa_amount'],
                    'transaction_type' => $validated['mpesa_transaction_type'],
                    'status' => $validated['mpesa_status'] ?? 'pending',
                    'confirmation_code' => $validated['mpesa_confirmation_code'],
                ]);
                $onboarding->update(['payment_id' => $mpesa->id]);
            }

            DB::commit();

            return response()->json([
                'status' => 'success',
                'message' => 'Onboarding created successfully',
                'data' => $onboarding->load('mpesa'),
            ], 201);
        } catch (QueryException $e) {
            DB::rollBack();
            Log::error('Onboarding creation failed: ' . $e->getMessage(), [
                'request_data' => $request->all(),
                'sql' => $e->getSql(),
                'bindings' => $e->getBindings(),
            ]);
            if ($e->getCode() === '23000') {
                if (str_contains($e->getMessage(), 'onboardings_user_id_unique')) {
                    return response()->json([
                        'status' => 'error',
                        'message' => 'Onboarding record already exists for this user',
                    ], 422);
                }
                if (str_contains($e->getMessage(), 'onboardings_clinic_id_foreign')) {
                    return response()->json([
                        'status' => 'error',
                        'message' => 'Invalid clinic ID: Clinic does not exist',
                    ], 422);
                }
                if (str_contains($e->getMessage(), 'onboardings_payer_id_foreign')) {
                    return response()->json([
                        'status' => 'error',
                        'message' => 'Invalid payer ID: Payer does not exist',
                    ], 422);
                }
                if (str_contains($e->getMessage(), 'onboardings_insurance_id_foreign')) {
                    return response()->json([
                        'status' => 'error',
                        'message' => 'Invalid insurance ID: Insurance record does not exist',
                    ], 422);
                }
            }
            return response()->json([
                'status' => 'error',
                'message' => 'An error occurred during onboarding',
                'error' => $e->getMessage(),
            ], 500);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Onboarding creation failed: ' . $e->getMessage(), [
                'request_data' => $request->all(),
                'trace' => $e->getTraceAsString(),
            ]);
            return response()->json([
                'status' => 'error',
                'message' => 'An unexpected error occurred',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function check(Request $request, $userId)
    {
        try {
            // Validate user_id
            $validator = Validator::make(['user_id' => $userId], [
                'user_id' => 'required|integer|exists:users,id',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Invalid user ID',
                    'errors' => $validator->errors(),
                ], 422);
            }

            // Check if user is authenticated and authorized
            $authUser = $request->user();
            if (!$authUser || $authUser->id != $userId) {
                Log::warning('Unauthorized onboarding check attempt', [
                    'user_id' => $authUser?->id,
                    'requested_user_id' => $userId,
                ]);
                return response()->json([
                    'status' => 'error',
                    'message' => 'Unauthorized to check onboarding status for this user',
                ], 403);
            }

            $exists = Onboarding::where('user_id', $userId)->exists();
            return response()->json([
                'status' => 'success',
                'exists' => $exists,
            ], 200);
        } catch (QueryException $e) {
            Log::error('Database error during onboarding check for user_id: ' . $userId, [
                'error' => $e->getMessage(),
                'sql' => $e->getSql(),
                'bindings' => $e->getBindings(),
            ]);
            return response()->json([
                'status' => 'error',
                'message' => 'Database error occurred while checking onboarding status',
                'error' => $e->getMessage(),
            ], 500);
        } catch (\Exception $e) {
            Log::error('Onboarding check failed for user_id: ' . $userId, [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to check onboarding status',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}