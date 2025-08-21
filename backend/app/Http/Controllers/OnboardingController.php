<?php

namespace App\Http\Controllers;

use App\Models\Onboarding;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Database\QueryException;

class OnboardingController extends Controller
{
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'user_id' => 'required|exists:users,id',
            'first_name' => 'required|string|max:255',
            'middle_name' => 'nullable|string|max:255',
            'last_name' => 'required|string|max:255',
            'date_of_birth' => 'required|date',
            'age' => 'required|integer|min:0',
            'sex' => 'required|in:male,female,other',
            'clinic_id' => 'required|exists:clinics,id',
            'date_of_onboarding' => 'required|date',
            'emergency_contact_name' => 'required|string|max:255',
            'emergency_contact_phone' => 'required|string|max:20',
            'emergency_contact_relation' => 'required|string|max:255',
            'consent_to_telehealth' => 'required|boolean',
            'consent_to_risks' => 'required|boolean',
            'consent_to_data_use' => 'required|boolean',
            'consent_date' => 'required|date',
            'payment_method' => 'nullable|in:mpesa,insurance',
            'payment_id' => 'nullable|integer',
            'payment_status' => 'nullable|in:pending,completed',
            'mpesa_number' => 'nullable|string|max:20',
            'mpesa_reference' => 'nullable|string|max:255',
            'insurance_provider' => 'nullable|string|max:255',
            'insurance_id' => 'nullable|exists:insurance,id',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            // Check for existing onboarding record
            if (Onboarding::where('user_id', $request->user_id)->exists()) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Onboarding record already exists for this user',
                ], 400);
            }

            $onboarding = Onboarding::create([
                'user_id' => $request->user_id,
                'first_name' => $request->first_name,
                'middle_name' => $request->middle_name,
                'last_name' => $request->last_name,
                'date_of_birth' => $request->date_of_birth,
                'age' => $request->age,
                'sex' => $request->sex,
                'clinic_id' => $request->clinic_id,
                'date_of_onboarding' => $request->date_of_onboarding,
                'emergency_contact_name' => $request->emergency_contact_name,
                'emergency_contact_phone' => $request->emergency_contact_phone,
                'emergency_contact_relation' => $request->emergency_contact_relation,
                'consent_to_telehealth' => $request->consent_to_telehealth,
                'consent_to_risks' => $request->consent_to_risks,
                'consent_to_data_use' => $request->consent_to_data_use,
                'consent_date' => $request->consent_date,
                'payment_method' => $request->payment_method,
                'payment_id' => $request->payment_id,
                'payment_status' => $request->payment_status,
                'mpesa_number' => $request->mpesa_number,
                'mpesa_reference' => $request->mpesa_reference,
                'insurance_provider' => $request->insurance_provider,
                'insurance_id' => $request->insurance_id,
                'is_active' => false,
            ]);

            return response()->json([
                'status' => 'success',
                'message' => 'Onboarding created successfully',
                'data' => $onboarding,
            ], 201);
        } catch (QueryException $e) {
            \Log::error('Onboarding creation failed: ' . $e->getMessage(), [
                'request_data' => $request->all(),
                'sql' => $e->getSql(),
                'bindings' => $e->getBindings(),
            ]);
            return response()->json([
                'status' => 'error',
                'message' => 'An error occurred during onboarding',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function check($userId)
    {
        try {
            $exists = Onboarding::where('user_id', $userId)->exists();
            return response()->json(['exists' => $exists], 200);
        } catch (\Exception $e) {
            \Log::error('Onboarding check failed for user_id: ' . $userId, ['error' => $e->getMessage()]);
            return response()->json(['error' => 'Server error'], 500);
        }
    }
}