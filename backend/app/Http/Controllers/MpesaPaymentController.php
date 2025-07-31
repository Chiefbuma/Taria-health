<?php

namespace App\Http\Controllers;

use App\Models\MpesaPayment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class MpesaPaymentController extends Controller
{
    public function initiate(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'phone_number' => 'required|string|regex:/^254[0-9]{9}$/',
            'amount' => 'required|numeric|min:1',
            'user_id' => 'required|exists:users,id',
        ]);

        if ($validator->fails()) {
            Log::error('M-Pesa initiation validation failed:', $validator->errors()->toArray());
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $mpesaPayment = MpesaPayment::create([
                'phone_number' => $request->phone_number,
                'amount' => $request->amount,
                'mpesa_reference' => 'SIM' . time() . rand(1000, 9999),
                'status' => 'pending',
                'client_name' => $request->user()->name,
                'transaction_type' => 'payment',
            ]);

            // Simulate M-Pesa STK push
            Log::info('Simulated M-Pesa STK push:', ['reference' => $mpesaPayment->mpesa_reference]);

            return response()->json([
                'success' => true,
                'message' => 'M-Pesa payment initiated. Enter code "CONF123" to verify.',
                'data' => [
                    'payment_id' => $mpesaPayment->id,
                    'reference' => $mpesaPayment->mpesa_reference,
                ]
            ], 201);

        } catch (\Exception $e) {
            Log::error('M-Pesa initiation error:', ['error' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'message' => 'Failed to initiate M-Pesa payment',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function verify(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'payment_id' => 'required|exists:mpesa_payments,id',
            'confirmation_code' => 'required|string|max:20',
        ]);

        if ($validator->fails()) {
            Log::error('M-Pesa verification validation failed:', $validator->errors()->toArray());
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $mpesaPayment = MpesaPayment::findOrFail($request->payment_id);

            // Simulate verification (accept "CONF123" as valid code)
            if ($request->confirmation_code === 'CONF123') {
                $mpesaPayment->update([
                    'confirmation_code' => $request->confirmation_code,
                    'status' => 'completed',
                ]);

                Log::info('M-Pesa payment verified:', ['payment_id' => $mpesaPayment->id]);

                return response()->json([
                    'success' => true,
                    'message' => 'M-Pesa payment verified successfully',
                    'data' => [
                        'payment_id' => $mpesaPayment->id,
                        'reference' => $mpesaPayment->mpesa_reference,
                        'amount' => $mpesaPayment->amount,
                        'phone_number' => $mpesaPayment->phone_number,
                        'status' => $mpesaPayment->status,
                    ]
                ], 200);
            } else {
                Log::warning('Invalid M-Pesa confirmation code:', ['code' => $request->confirmation_code]);
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid confirmation code'
                ], 400);
            }

        } catch (\Exception $e) {
            Log::error('M-Pesa verification error:', ['error' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'message' => 'Failed to verify M-Pesa payment',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}