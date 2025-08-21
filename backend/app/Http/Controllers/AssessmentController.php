<?php

namespace App\Http\Controllers;

use App\Models\Assessment;
use App\Models\Onboarding;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class AssessmentController extends Controller
{
    public function index(Request $request)
    {
        try {
            $type = $request->query('type');
            $validTypes = ['weekly', 'three_monthly', 'six_monthly'];

            if ($type && !in_array($type, $validTypes)) {
                return response()->json([
                    'message' => 'Invalid assessment type',
                    'errors' => ['type' => ['Invalid assessment type. Must be weekly, three_monthly, or six_monthly.']],
                ], 422);
            }

            $query = Assessment::with(['onboarding' => function ($query) {
                $query->select('id', 'user_id', 'first_name', 'last_name', 'patient_no', 'payment_status', 'initial_followup_date', 'diagnoses');
            }]);

            if ($type) {
                $query->where('type', $type);
            }

            $assessments = $query->get();

            return response()->json([
                'message' => 'Assessments retrieved successfully',
                'data' => $assessments,
            ], 200);
        } catch (\Exception $e) {
            Log::error('Error fetching assessments: ' . $e->getMessage());
            return response()->json([
                'message' => 'Failed to fetch assessments',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function store(Request $request)
    {
        try {
            $rules = [
                'onboarding_id' => 'required|exists:onboardings,id',
                'type' => 'required|in:weekly,three_monthly,six_monthly',
                'assessment_date' => 'required|date',
                'revenue' => 'required|numeric|min:0',
                'physician' => 'required|string|max:255',
                'navigator' => 'required|string|max:255',
            ];

            $onboarding = Onboarding::find($request->onboarding_id);
            if (!$onboarding || $onboarding->payment_status !== 'approved') {
                return response()->json([
                    'message' => 'Invalid onboarding or payment not approved',
                    'errors' => ['onboarding_id' => ['Onboarding must exist and have approved payment status']],
                ], 422);
            }

            $diagnoses = $onboarding->diagnoses ?? [];
            $type = $request->type;

            // Define required fields based on assessment type and diagnoses
            if ($type === 'weekly') {
                if (in_array('diabetes', $diagnoses)) {
                    $rules['hba1c'] = 'required|numeric';
                    $rules['bp'] = 'required|string';
                    $rules['weight'] = 'required|numeric';
                    $rules['bmi'] = 'required|numeric';
                    $rules['serum_creatinine'] = 'required|numeric';
                }
                if (in_array('cardiovascular', $diagnoses)) {
                    $rules['ldl'] = 'required|numeric';
                    $rules['bp'] = 'required|string';
                }
                if (in_array('obesity', $diagnoses)) {
                    $rules['weight'] = 'required|numeric';
                    $rules['bmi'] = 'required|numeric';
                    $rules['physical_activity_level'] = 'required|string';
                }
            } elseif ($type === 'three_monthly' || $type === 'six_monthly') {
                if (in_array('diabetes', $diagnoses)) {
                    $rules['hba1c'] = 'required|numeric';
                    $rules['bp'] = 'required|string';
                    $rules['weight'] = 'required|numeric';
                    $rules['bmi'] = 'required|numeric';
                    $rules['serum_creatinine'] = 'required|numeric';
                }
                if (in_array('cardiovascular', $diagnoses)) {
                    $rules['ldl'] = 'required|numeric';
                    $rules['bp'] = 'required|string';
                    $rules['ecg'] = 'required|string';
                }
                if (in_array('obesity', $diagnoses)) {
                    $rules['weight'] = 'required|numeric';
                    $rules['bmi'] = 'required|numeric';
                }
                $rules['ecg'] = 'required|string'; // ECG is always required for three_monthly and six_monthly
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

            $assessment = Assessment::create($data);

            return response()->json([
                'message' => 'Assessment created successfully',
                'data' => $assessment->load('onboarding'),
            ], 201);
        } catch (\Exception $e) {
            Log::error('Error creating assessment: ' . $e->getMessage());
            return response()->json([
                'message' => 'Failed to create assessment',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function show($id)
    {
        try {
            $assessment = Assessment::with(['onboarding' => function ($query) {
                $query->select('id', 'user_id', 'first_name', 'last_name', 'patient_no', 'payment_status', 'initial_followup_date', 'diagnoses');
            }])->find($id);

            if (!$assessment) {
                return response()->json([
                    'message' => 'Assessment not found',
                ], 404);
            }

            return response()->json([
                'message' => 'Assessment retrieved successfully',
                'data' => $assessment,
            ], 200);
        } catch (\Exception $e) {
            Log::error('Error fetching assessment: ' . $e->getMessage());
            return response()->json([
                'message' => 'Failed to fetch assessment',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function update(Request $request, $id)
    {
        try {
            $assessment = Assessment::find($id);
            if (!$assessment) {
                return response()->json([
                    'message' => 'Assessment not found',
                ], 404);
            }

            $rules = [
                'onboarding_id' => 'required|exists:onboardings,id',
                'type' => 'required|in:weekly,three_monthly,six_monthly',
                'assessment_date' => 'required|date',
                'revenue' => 'required|numeric|min:0',
                'physician' => 'required|string|max:255',
                'navigator' => 'required|string|max:255',
            ];

            $onboarding = Onboarding::find($request->onboarding_id);
            if (!$onboarding || $onboarding->payment_status !== 'approved') {
                return response()->json([
                    'message' => 'Invalid onboarding or payment not approved',
                    'errors' => ['onboarding_id' => ['Onboarding must exist and have approved payment status']],
                ], 422);
            }

            $diagnoses = $onboarding->diagnoses ?? [];
            $type = $request->type;

            if ($type === 'weekly') {
                if (in_array('diabetes', $diagnoses)) {
                    $rules['hba1c'] = 'required|numeric';
                    $rules['bp'] = 'required|string';
                    $rules['weight'] = 'required|numeric';
                    $rules['bmi'] = 'required|numeric';
                    $rules['serum_creatinine'] = 'required|numeric';
                }
                if (in_array('cardiovascular', $diagnoses)) {
                    $rules['ldl'] = 'required|numeric';
                    $rules['bp'] = 'required|string';
                }
                if (in_array('obesity', $diagnoses)) {
                    $rules['weight'] = 'required|numeric';
                    $rules['bmi'] = 'required|numeric';
                    $rules['physical_activity_level'] = 'required|string';
                }
            } elseif ($type === 'three_monthly' || $type === 'six_monthly') {
                if (in_array('diabetes', $diagnoses)) {
                    $rules['hba1c'] = 'required|numeric';
                    $rules['bp'] = 'required|string';
                    $rules['weight'] = 'required|numeric';
                    $rules['bmi'] = 'required|numeric';
                    $rules['serum_creatinine'] = 'required|numeric';
                }
                if (in_array('cardiovascular', $diagnoses)) {
                    $rules['ldl'] = 'required|numeric';
                    $rules['bp'] = 'required|string';
                    $rules['ecg'] = 'required|string';
                }
                if (in_array('obesity', $diagnoses)) {
                    $rules['weight'] = 'required|numeric';
                    $rules['bmi'] = 'required|numeric';
                }
                $rules['ecg'] = 'required|string';
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

            $assessment->update($data);

            return response()->json([
                'message' => 'Assessment updated successfully',
                'data' => $assessment->load('onboarding'),
            ], 200);
        } catch (\Exception $e) {
            Log::error('Error updating assessment: ' . $e->getMessage());
            return response()->json([
                'message' => 'Failed to update assessment',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function destroy(Request $request, $id)
    {
        try {
            $type = $request->query('type');
            $validTypes = ['weekly', 'three_monthly', 'six_monthly'];

            if ($type && !in_array($type, $validTypes)) {
                return response()->json([
                    'message' => 'Invalid assessment type',
                    'errors' => ['type' => ['Invalid assessment type. Must be weekly, three_monthly, or six_monthly.']],
                ], 422);
            }

            $assessment = Assessment::find($id);
            if (!$assessment) {
                return response()->json([
                    'message' => 'Assessment not found',
                ], 404);
            }

            if ($type && $assessment->type !== $type) {
                return response()->json([
                    'message' => 'Assessment type mismatch',
                    'errors' => ['type' => ['The assessment does not match the specified type']],
                ], 422);
            }

            $assessment->delete();

            return response()->json([
                'message' => 'Assessment deleted successfully',
            ], 200);
        } catch (\Exception $e) {
            Log::error('Error deleting assessment: ' . $e->getMessage());
            return response()->json([
                'message' => 'Failed to delete assessment',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}