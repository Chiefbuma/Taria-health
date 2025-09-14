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
use Carbon\Carbon;

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
        $today = Carbon::today()->format('Y-m-d');

        // Define validation rules for required fields only
        $validator = Validator::make($request->all(), [
            'user_id' => 'required|integer|exists:users,id|unique:onboardings,user_id',
            'first_name' => 'required|string|max:255',
            'last_name' => 'required|string|max:255',
            'date_of_birth' => 'required|date|before_or_equal:' . $today,
            'clinic_id' => 'required|integer|exists:clinics,id',
            'age' => 'required|integer|min:0',
            'sex' => 'required|string|in:male,female,other',
            'date_of_onboarding' => 'required|date',
            'emergency_contact_name' => 'required|string|max:255',
            'emergency_contact_relation' => 'required|string|max:255',
            
            // Optional fields
            'consent_date' => 'nullable|date',
            'payment_method' => 'nullable|string|in:mpesa,insurance',
            'payment_status' => 'nullable|string|in:pending,completed',
            'middle_name' => 'nullable|string|max:255',
            'emr_number' => 'nullable|string|max:255',
            'payer_id' => 'nullable|integer|exists:payers,id',
            'diagnoses' => 'nullable|array',
            'diagnoses.*' => 'string|max:255',
            'date_of_diagnosis' => 'nullable|date',
            'medications' => 'nullable|json',
            'emergency_contact_phone' => 'nullable|string|max:20',
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
            'consent_to_telehealth' => 'nullable|boolean',
            'consent_to_risks' => 'nullable|boolean',
            'consent_to_data_use' => 'nullable|boolean',
            'activation_code' => 'nullable|string|max:255',
            'is_active' => 'nullable|boolean',
            'phone_number' => 'nullable|string|regex:/^0[0-9]{9}$/|size:10',
            'mpesa_reference' => 'nullable|string|max:255',
            'mpesa_client_name' => 'nullable|string|max:255',
            'mpesa_amount' => 'nullable|numeric|min:0',
            'mpesa_transaction_type' => 'nullable|string|max:255',
            'mpesa_status' => 'nullable|string|in:pending,completed,failed',
            'mpesa_confirmation_code' => 'nullable|string|max:255',
            'policy_number' => 'nullable|string|max:255',
            'insurance_record_id' => 'nullable|integer|exists:insurance,id',
            'payment_id' => 'nullable|integer',
        ]);

        // Custom validation for consents
        $validator->after(function ($validator) use ($request) {
            if (!$request->boolean('consent_to_telehealth') &&
                !$request->boolean('consent_to_risks') &&
                !$request->boolean('consent_to_data_use')) {
                $validator->errors()->add('consents', 'At least one consent option (telehealth, risks, or data use) must be selected.');
            }
        });

        // Conditional validation for payment method
        if ($request->input('payment_method') === 'mpesa') {
            $mpesaValidator = Validator::make($request->all(), [
                'phone_number' => 'required|string|regex:/^0[0-9]{9}$/|size:10',
                'mpesa_reference' => 'required|string|max:255',
                'mpesa_client_name' => 'required|string|max:255',
                'mpesa_amount' => 'required|numeric|min:0',
                'mpesa_transaction_type' => 'required|string|max:255',
            ]);
            if ($mpesaValidator->fails()) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'M-Pesa validation failed',
                    'errors' => $mpesaValidator->errors(),
                ], 422);
            }
        } elseif ($request->input('payment_method') === 'insurance') {
            $insuranceValidator = Validator::make($request->all(), [
                'payer_id' => 'required|integer|exists:payers,id',
                'policy_number' => 'required|string|max:255',
                'insurance_record_id' => 'required|integer|exists:insurance,id',
            ]);
            if ($insuranceValidator->fails()) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Insurance validation failed',
                    'errors' => $insuranceValidator->errors(),
                ], 422);
            }
        }

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $validated = $validator->validated();

        // Clear fields based on payment method
        if ($validated['payment_method'] === 'mpesa') {
            $validated['policy_number'] = null;
            $validated['insurance_record_id'] = null;
        } elseif ($validated['payment_method'] === 'insurance') {
            $validated['phone_number'] = null;
            $validated['mpesa_reference'] = null;
            $validated['mpesa_client_name'] = null;
            $validated['mpesa_amount'] = null;
            $validated['mpesa_transaction_type'] = null;
            $validated['mpesa_status'] = null;
            $validated['mpesa_confirmation_code'] = null;
        }

        DB::beginTransaction();

        try {
            // Prepare onboarding data
            $onboardingData = [
                'user_id' => $validated['user_id'],
                'first_name' => $validated['first_name'],
                'middle_name' => $validated['middle_name'] ?? null,
                'last_name' => $validated['last_name'],
                'date_of_birth' => $validated['date_of_birth'],
                'emr_number' => $validated['emr_number'] ?? null,
                'payer_id' => $validated['payer_id'] ?? null,
                'clinic_id' => $validated['clinic_id'],
                'diagnoses' => $validated['diagnoses'] ? json_encode($validated['diagnoses']) : null,
                'date_of_diagnosis' => $validated['date_of_diagnosis'] ?? null,
                'medications' => $validated['medications'] ?? null,
                'age' => $validated['age'],
                'sex' => $validated['sex'],
                'date_of_onboarding' => $validated['date_of_onboarding'],
                'emergency_contact_name' => $validated['emergency_contact_name'],
                'emergency_contact_phone' => $validated['emergency_contact_phone'] ?? null,
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
                'is_active' => $validated['is_active'] ?? true,
                'consent_to_telehealth' => $validated['consent_to_telehealth'] ?? false,
                'consent_to_risks' => $validated['consent_to_risks'] ?? false,
                'consent_to_data_use' => $validated['consent_to_data_use'] ?? false,
                'payment_method' => $validated['payment_method'],
                'payment_status' => $validated['payment_status'],
                'insurance_id' => $validated['insurance_record_id'] ?? null,
                'payment_id' => $validated['payment_id'] ?? null,
            ];

            // Create onboarding record
            $onboarding = Onboarding::create($onboardingData);

            // Create M-Pesa record if payment_method is mpesa
            if ($validated['payment_method'] === 'mpesa') {
                $mpesa = Mpesa::create([
                    'user_id' => $validated['user_id'],
                    'onboarding_id' => $onboarding->id,
                    'phone_number' => $validated['phone_number'],
                    'mpesa_reference' => $validated['mpesa_reference'],
                    'client_name' => $validated['mpesa_client_name'],
                    'amount' => $validated['mpesa_amount'],
                    'transaction_type' => $validated['mpesa_transaction_type'],
                    'status' => $validated['mpesa_status'] ?? 'pending',
                    'confirmation_code' => $validated['mpesa_confirmation_code'] ?? null,
                ]);
                $onboarding->update(['payment_id' => $mpesa->id]);
            } elseif ($validated['payment_method'] === 'insurance') {
                // Update insurance record with onboarding_id
                Insurance::where('id', $validated['insurance_record_id'])
                    ->update(['onboarding_id' => $onboarding->id]);
                $onboarding->update(['payment_id' => $validated['insurance_record_id']]);
            }

            DB::commit();

            Log::info('Onboarding created successfully:', [
                'onboarding_id' => $onboarding->id,
                'user_id' => $validated['user_id'],
            ]);

            return response()->json([
                'status' => 'success',
                'message' => 'Onboarding created successfully',
                'data' => $onboarding->load('mpesa'),
            ], 201);
        } catch (\Illuminate\Database\QueryException $e) {
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
                if (str_contains($e->getMessage(), 'cannot be null')) {
                    return response()->json([
                        'status' => 'error',
                        'message' => 'Required field cannot be null',
                        'error' => $e->getMessage(),
                    ], 422);
                }
            }

            return response()->json([
                'status' => 'error',
                'message' => 'An error occurred during onboarding',
                'error' => env('APP_DEBUG') ? $e->getMessage() : 'Database error',
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
                'error' => env('APP_DEBUG') ? $e->getMessage() : 'Server error',
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