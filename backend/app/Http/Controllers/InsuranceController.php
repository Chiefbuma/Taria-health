<?php

namespace App\Http\Controllers;

use App\Models\Insurance;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class InsuranceController extends Controller
{
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'insurance_provider' => 'required|string|max:255',
            'policy_number' => 'required|string|max:255|unique:insurance,policy_number',
            'insurance_type' => 'required|string|max:255',
            'onboarding_id' => 'nullable|exists:onboardings,id',
            'user_id' => 'required|exists:users,id', // Add validation for user_id
        ]);

        if ($validator->fails()) {
            Log::error('Insurance validation failed:', $validator->errors()->toArray());
            return response()->json([
                'success' => false,
                'message' => 'Validation errors',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $insurance = Insurance::create([
                'insurance_provider' => $request->insurance_provider,
                'policy_number' => $request->policy_number,
                'insurance_type' => $request->insurance_type,
                'onboarding_id' => $request->onboarding_id,
                'is_approved' => false,
                'user_id' => $request->user_id, // Set user_id from request
            ]);

            Log::info('Insurance record created:', ['insurance_id' => $insurance->id]);

            return response()->json([
                'success' => true,
                'message' => 'Insurance record created successfully',
                'data' => [
                    'insurance' => $insurance
                ]
            ], 201);

        } catch (\Exception $e) {
            Log::error('Insurance creation error:', ['error' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'message' => 'Failed to create insurance record',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function update(Request $request, $id)
    {
        $insurance = Insurance::findOrFail($id);

        $validator = Validator::make($request->all(), [
            'onboarding_id' => 'required|exists:onboardings,id',
            'insurance_provider' => 'required|string|max:255',
            'policy_number' => 'required|string|max:255|unique:insurance,policy_number,' . $insurance->id,
            'insurance_type' => 'required|string|max:255',
            'user_id' => 'required|exists:users,id', // Add validation for user_id
        ]);

        if ($validator->fails()) {
            Log::error('Insurance update validation failed:', $validator->errors()->toArray());
            return response()->json([
                'success' => false,
                'message' => 'Validation errors',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $insurance->update([
                'onboarding_id' => $request->onboarding_id,
                'insurance_provider' => $request->insurance_provider,
                'policy_number' => $request->policy_number,
                'insurance_type' => $request->insurance_type,
                'user_id' => $request->user_id, // Update user_id
            ]);

            Log::info('Insurance record updated:', ['insurance_id' => $insurance->id]);

            return response()->json([
                'success' => true,
                'message' => 'Insurance record updated successfully',
                'data' => $insurance
            ], 200);

        } catch (\Exception $e) {
            Log::error('Insurance update error:', ['error' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'message' => 'Failed to update insurance record',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}