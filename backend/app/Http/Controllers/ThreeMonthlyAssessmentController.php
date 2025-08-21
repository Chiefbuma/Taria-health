<?php

namespace App\Http\Controllers;

use App\Models\ThreeMonthlyAssessment;
use App\Models\Onboarding;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;

class ThreeMonthlyAssessmentController extends Controller
{
    public function index(Request $request)
    {
        try {
            $assessments = ThreeMonthlyAssessment::with(['onboarding' => function ($query) {
                $query->select('id', 'user_id', 'first_name', 'last_name', 'patient_no', 'payment_status', 'initial_consultation_date', 'diagnoses');
            }])->get();

            return response()->json([
                'message' => 'Three-monthly assessments retrieved successfully',
                'data' => $assessments,
            ], 200);
        } catch (\Exception $e) {
            Log::error('Error fetching three-monthly assessments: ' . $e->getMessage());
            return response()->json([
                'message' => 'Failed to fetch three-monthly assessments',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function store(Request $request)
    {
        try {
            $rules = [
                'onboarding_id' => 'required|exists:onboardings,id',
                'assessment_date' => 'required|date',
                'revenue' => 'required|numeric|min:0',
                'hba1c' => 'nullable|numeric',
                'ldl' => 'nullable|numeric',
                'bp' => 'nullable|string',
                'weight' => 'nullable|numeric',
                'height' => 'nullable|numeric',
                'bmi' => 'nullable|numeric',
                'serum_creatinine' => 'nullable|numeric',
                'physical_activity_level' => 'nullable|string',
                'ecg' => 'nullable|string',
                'nutrition' => 'nullable|string',
                'exercise' => 'nullable|string',
                'sleep_mental_health' => 'nullable|string',
                'medication_adherence' => 'nullable|string',
            ];

            $onboarding = Onboarding::find($request->onboarding_id);
            if (!$onboarding || $onboarding->payment_status !== 'approved') {
                return response()->json([
                    'message' => 'Invalid onboarding or payment not approved',
                    'errors' => ['onboarding_id' => ['Onboarding must exist and have approved payment status']],
                ], 422);
            }

            $validator = Validator::make($request->all(), $rules);

            if ($validator->fails()) {
                return response()->json([
                    'message' => 'Validation failed',
                    'errors' => $validator->errors(),
                ], 422);
            }

            $data = $validator->validated();
            $data['user_id'] = $onboarding->user_id;

            // Calculate BMI if height and weight are provided
            if (isset($data['height']) && isset($data['weight']) && $data['height'] > 0) {
                $data['bmi'] = ($data['weight'] / ($data['height'] * $data['height'])) * 703;
            } else {
                $data['bmi'] = null;
            }

            $assessment = ThreeMonthlyAssessment::create($data);

            return response()->json([
                'message' => 'Three-monthly assessment created successfully',
                'data' => $assessment->load('onboarding'),
            ], 201);
        } catch (\Exception $e) {
            Log::error('Error creating three-monthly assessment: ' . $e->getMessage());
            return response()->json([
                'message' => 'Failed to create three-monthly assessment',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function show($id)
    {
        try {
            $assessment = ThreeMonthlyAssessment::with(['onboarding' => function ($query) {
                $query->select('id', 'user_id', 'first_name', 'last_name', 'patient_no', 'payment_status', 'initial_consultation_date', 'diagnoses');
            }])->find($id);

            if (!$assessment) {
                return response()->json([
                    'message' => 'Three-monthly assessment not found',
                ], 404);
            }

            return response()->json([
                'message' => 'Three-monthly assessment retrieved successfully',
                'data' => $assessment,
            ], 200);
        } catch (\Exception $e) {
            Log::error('Error fetching three-monthly assessment: ' . $e->getMessage());
            return response()->json([
                'message' => 'Failed to fetch three-monthly assessment',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function update(Request $request, $id)
    {
        try {
            $assessment = ThreeMonthlyAssessment::find($id);
            if (!$assessment) {
                return response()->json([
                    'message' => 'Three-monthly assessment not found',
                ], 404);
            }

            $rules = [
                'onboarding_id' => 'required|exists:onboardings,id',
                'assessment_date' => 'required|date',
                'revenue' => 'required|numeric|min:0',
                'hba1c' => 'nullable|numeric',
                'ldl' => 'nullable|numeric',
                'bp' => 'nullable|string',
                'weight' => 'nullable|numeric',
                'height' => 'nullable|numeric',
                'bmi' => 'nullable|numeric',
                'serum_creatinine' => 'nullable|numeric',
                'physical_activity_level' => 'nullable|string',
                'ecg' => 'nullable|string',
                'nutrition' => 'nullable|string',
                'exercise' => 'nullable|string',
                'sleep_mental_health' => 'nullable|string',
                'medication_adherence' => 'nullable|string',
            ];

            $onboarding = Onboarding::find($request->onboarding_id);
            if (!$onboarding || $onboarding->payment_status !== 'approved') {
                return response()->json([
                    'message' => 'Invalid onboarding or payment not approved',
                    'errors' => ['onboarding_id' => ['Onboarding must exist and have approved payment status']],
                ], 422);
            }

            $validator = Validator::make($request->all(), $rules);

            if ($validator->fails()) {
                return response()->json([
                    'message' => 'Validation failed',
                    'errors' => $validator->errors(),
                ], 422);
            }

            $data = $validator->validated();
            $data['user_id'] = $onboarding->user_id;

            // Calculate BMI if height and weight are provided
            if (isset($data['height']) && isset($data['weight']) && $data['height'] > 0) {
                $data['bmi'] = ($data['weight'] / ($data['height'] * $data['height'])) * 703;
            } else {
                $data['bmi'] = null;
            }

            $assessment->update($data);

            return response()->json([
                'message' => 'Three-monthly assessment updated successfully',
                'data' => $assessment->load('onboarding'),
            ], 200);
        } catch (\Exception $e) {
            Log::error('Error updating three-monthly assessment: ' . $e->getMessage());
            return response()->json([
                'message' => 'Failed to update three-monthly assessment',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function destroy($id)
    {
        try {
            $assessment = ThreeMonthlyAssessment::find($id);
            if (!$assessment) {
                return response()->json([
                    'message' => 'Three-monthly assessment not found',
                ], 404);
            }

            $assessment->delete();

            return response()->json([
                'message' => 'Three-monthly assessment deleted successfully',
            ], 200);
        } catch (\Exception $e) {
            Log::error('Error deleting three-monthly assessment: ' . $e->getMessage());
            return response()->json([
                'message' => 'Failed to delete three-monthly assessment',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}