<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Payment;
use App\Models\Onboarding;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;

class PaymentController extends Controller
{
    /**
     * Initiate M-Pesa Payment
     */
    public function initiateMpesa(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'onboarding_id' => 'required|exists:onboardings,id',
            'phone_number' => 'required|string|max:12',
            'amount' => 'required|numeric|min:1',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            // In production, call actual M-Pesa API here
            $payment = Payment::create([
                'onboarding_id' => $request->onboarding_id,
                'payment_method' => 'mpesa',
                'phone_number' => $request->phone_number,
                'amount' => $request->amount,
                'status' => 'pending',
                'metadata' => [
                    'initiated_at' => now()->toDateTimeString(),
                    'ip' => $request->ip()
                ]
            ]);

            // Simulate M-Pesa response
            $payment->update([
                'mpesa_reference' => 'MPE' . now()->format('YmdHis'),
                'transaction_id' => 'TRX' . uniqid()
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Payment initiated successfully',
                'checkout_request_id' => $payment->transaction_id,
                'payment' => $payment
            ]);

        } catch (\Exception $e) {
            Log::error('Mpesa Initiation Error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to initiate payment'
            ], 500);
        }
    }

    /**
     * Verify/Callback for M-Pesa
     */
    public function verifyMpesa(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'transaction_id' => 'required|string',
            'mpesa_code' => 'required|string'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $payment = Payment::where('transaction_id', $request->transaction_id)
                            ->firstOrFail();

            // In production, verify with M-Pesa API
            $payment->update([
                'status' => 'completed',
                'mpesa_reference' => $request->mpesa_code,
                'metadata->verified_at' => now()->toDateTimeString(),
                'metadata->callback_data' => $request->all()
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Payment verified successfully',
                'payment' => $payment
            ]);

        } catch (\Exception $e) {
            Log::error('Mpesa Verification Error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Payment verification failed'
            ], 400);
        }
    }

    /**
     * Handle Insurance Payment
     */
    public function processInsurance(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'onboarding_id' => 'required|exists:onboardings,id',
            'insurance_provider' => 'required|string',
            'policy_number' => 'required|string',
            'amount' => 'required|numeric'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $payment = Payment::create([
                'onboarding_id' => $request->onboarding_id,
                'payment_method' => 'insurance',
                'insurance_provider' => $request->insurance_provider,
                'policy_number' => $request->policy_number,
                'amount' => $request->amount,
                'status' => 'pending_verification',
                'metadata' => [
                    'submitted_at' => now()->toDateTimeString()
                ]
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Insurance payment submitted for verification',
                'payment' => $payment
            ]);

        } catch (\Exception $e) {
            Log::error('Insurance Payment Error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to process insurance payment'
            ], 500);
        }
    }

    /**
     * Get Payment Status
     */
    public function checkStatus($id)
    {
        try {
            $payment = Payment::findOrFail($id);

            return response()->json([
                'success' => true,
                'status' => $payment->status,
                'payment' => $payment
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Payment not found'
            ], 404);
        }
    }
}