<?php

namespace App\Http\Controllers;

use App\Models\Onboarding;
use App\Models\MpesaPayment;
use App\Models\Insurance;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class OnboardingController extends Controller
{
    public function store(Request $request)
    {
        Log::info('Onboarding request received:', $request->all());

        $validator = Validator::make($request->all(), [
            'user_id' => 'required|exists:users,id',
            'first_name' => 'required|string|max:255',
            'last_name' => 'required|string|max:255',
            'patient_no' => 'required|string|max:255|unique:onboardings,patient_no',
            'clinic_id' => 'required|exists:clinics,id',
            'age' => 'required|integer|min:0|max:120',
            'sex' => 'required|in:male,female,other',
            'date_of_onboarding' => 'required|date',
            'emergency_contact_name' => 'required|string|max:255',
            'emergency_contact_phone' => 'required|string|max:20',
            'emergency_contact_relation' => 'required|string|max:255',
            'consent_to_telehealth' => 'required|boolean',
            'consent_to_risks' => 'required|boolean',
            'consent_to_data_use' => 'required|boolean',
            'consent_date' => 'required|date',
            'payment_method' => 'required|in:mpesa,insurance',
            'payment_id' => 'required|integer',
        ]);

        if ($validator->fails()) {
            Log::error('Validation failed:', $validator->errors()->toArray());
            return response()->json([
                'status' => 'error',
                'message' => 'Validation errors',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            // Check if user is already onboarded
            if (Onboarding::where('user_id', $request->user_id)->exists()) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'User already onboarded'
                ], 409);
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
                        'status' => 'error',
                        'message' => 'M-Pesa payment not completed or not found'
                    ], 400);
                }
                $paymentVerified = true;
            } elseif ($paymentMethod === 'insurance') {
                $payment = Insurance::where('id', $paymentId)->first();
                if (!$payment) {
                    return response()->json([
                        'status' => 'error',
                        'message' => 'Insurance record not found'
                    ], 400);
                }
                $paymentVerified = true;
            }

            if (!$paymentVerified) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Payment verification failed'
                ], 400);
            }

            // Create onboarding and update payment record in a transaction
            $onboarding = DB::transaction(function () use ($request, $paymentMethod, $paymentId) {
                $onboarding = Onboarding::create([
                    'user_id' => $request->user_id,
                    'first_name' => $request->first_name,
                    'last_name' => $request->last_name,
                    'patient_no' => $request->patient_no,
                    'clinic_id' => $request->clinic_id,
                    'age' => $request->age,
                    'sex' => $request->sex,
                    'date_of_onboarding' => $request->date_of_onboarding,
                    'emergency_contact_name' => $request->emergency_contact_name,
                    'emergency_contact_phone' => $request->emergency_contact_phone,
                    'emergency_contact_relation' => $request->emergency_contact_relation,
                    'consent_to_telehealth' => $request->consent_to_telehealth,
                    'consent_to_risks' => $request->consent_to_risks,
                    'consent_to_data_use' => $request->consent_to_data_use,
                    'consent_date' => $request->consent_date,
                    'payment_method' => $paymentMethod,
                    'payment_id' => $paymentId,
                    'is_active' => true,
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
                'status' => 'success',
                'message' => 'Patient onboarded successfully',
                'data' => [
                    'onboarding' => $onboarding,
                    'payment_method' => $paymentMethod,
                    'payment_id' => $paymentId
                ]
            ], 201);

        } catch (\Exception $e) {
            Log::error('Onboarding error:', ['error' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);
            return response()->json([
                'status' => 'error',
                'message' => 'An error occurred during onboarding',
                'error' => env('APP_DEBUG') ? $e->getMessage() : null
            ], 500);
        }
    }
}