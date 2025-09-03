<?php

namespace App\Http\Controllers;

use App\Models\Insurance;
use App\Models\Onboarding;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;

class InsuranceController extends Controller
{
    public function store(Request $request)
    {
        try {
            // Enhanced logging to capture the exact is_approved value
            Log::info('Create insurance request:', [
                'input' => $request->all(),
                'files' => $request->files->all(),
                'is_approved_value' => $request->input('is_approved'),
                'is_approved_type' => gettype($request->input('is_approved'))
            ]);

            $validator = Validator::make($request->all(), [
                'insurance_provider' => 'required|string|max:255',
                'policy_number' => 'required|string|max:255|unique:insurance,policy_number',
                'claim_amount' => 'nullable|numeric|min:0',
                'user_id' => 'required|exists:users,id',
                'is_approved' => 'nullable',
                'payment_date' => 'nullable|date',
                'approval_document' => 'nullable|file|mimes:pdf,jpg,png|max:10240',
            ]);

            if ($validator->fails()) {
                Log::error('Validation errors:', [
                    'errors' => $validator->errors(),
                    'input' => $request->all(),
                    'files' => $request->files->all(),
                    'is_approved_value' => $request->input('is_approved'),
                    'is_approved_type' => gettype($request->input('is_approved'))
                ]);
                return response()->json([
                    'success' => false,
                    'errors' => $validator->errors()
                ], 422);
            }

            $data = array_merge([
                'is_approved' => 'pending'
            ], $request->only([
                'insurance_provider',
                'policy_number',
                'claim_amount',
                'user_id',
                'is_approved',
                'payment_date'
            ]));

            if ($request->hasFile('approval_document')) {
                $file = $request->file('approval_document');
                $fileName = time() . '_' . $file->getClientOriginalName();
                $path = $file->storeAs('insurance_docs', $fileName, 'public');
                $data['approval_document_path'] = $path;
                $data['approval_document_name'] = $file->getClientOriginalName();
            }

            // Create the insurance record
            $insurance = Insurance::create($data);

            return response()->json([
                'success' => true,
                'data' => $insurance
            ], 201);
        } catch (\Exception $e) {
            Log::error('Insurance creation error:', [
                'error' => $e->getMessage(),
                'is_approved_value' => $request->input('is_approved'),
                'is_approved_type' => gettype($request->input('is_approved'))
            ]);
            return response()->json([
                'success' => false,
                'message' => 'Failed to create insurance record: ' . $e->getMessage()
            ], 500);
        }
    }

    public function index()
    {
        try {
            $insurances = Insurance::all();
            return response()->json([
                'success' => true,
                'data' => $insurances
            ], 200);
        } catch (\Exception $e) {
            Log::error('Failed to fetch insurances:', ['error' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch insurance records'
            ], 500);
        }
    }

    public function show($id)
    {
        try {
            $insurance = Insurance::findOrFail($id);
            return response()->json([
                'success' => true,
                'data' => $insurance
            ], 200);
        } catch (\Exception $e) {
            Log::error('Failed to fetch insurance:', ['id' => $id, 'error' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'message' => 'Insurance record not found'
            ], 404);
        }
    }

    public function update(Request $request, $id)
    {
        try {
            Log::info('Update insurance request:', [
                'id' => $id,
                'input' => $request->all(),
                'files' => $request->files->all(),
                'is_approved_value' => $request->input('is_approved'),
                'is_approved_type' => gettype($request->input('is_approved'))
            ]);

            $insurance = Insurance::findOrFail($id);

            $validator = Validator::make($request->all(), [
                'insurance_provider' => 'nullable|string|max:255',
                'policy_number' => 'nullable|string|max:255|unique:insurance,policy_number,' . $id,
                'claim_amount' => 'required|numeric|min:0',
                'user_id' => 'nullable|exists:users,id',
                'is_approved' => 'nullable|string',
                'payment_date' => 'nullable|date',
                'approval_document' => 'required|file|mimes:pdf,jpg,png|max:10240',
            ]);

            if ($validator->fails()) {
                Log::error('Validation errors:', [
                    'errors' => $validator->errors(),
                    'input' => $request->all(),
                    'files' => $request->files->all(),
                    'is_approved_value' => $request->input('is_approved'),
                    'is_approved_type' => gettype($request->input('is_approved'))
                ]);
                return response()->json([
                    'success' => false,
                    'errors' => $validator->errors()
                ], 422);
            }

            $data = array_merge([
                'is_approved' => 'pending'
            ], $request->only([
                'insurance_provider',
                'policy_number',
                'claim_amount',
                'user_id',
                'is_approved',
                'payment_date'
            ]));

            if ($request->hasFile('approval_document')) {
                if ($insurance->approval_document_path) {
                    Storage::disk('public')->delete($insurance->approval_document_path);
                }
                $file = $request->file('approval_document');
                $fileName = time() . '_' . $file->getClientOriginalName();
                $path = $file->storeAs('insurance_docs', $fileName, 'public');
                $data['approval_document_path'] = $path;
                $data['approval_document_name'] = $file->getClientOriginalName();
            }

            // Update the insurance record
            $insurance->update($data);

            return response()->json([
                'success' => true,
                'data' => $insurance
            ], 200);
        } catch (\Exception $e) {
            Log::error('Insurance update error:', [
                'error' => $e->getMessage(),
                'is_approved_value' => $request->input('is_approved'),
                'is_approved_type' => gettype($request->input('is_approved'))
            ]);
            return response()->json([
                'success' => false,
                'message' => 'Failed to update insurance: ' . $e->getMessage()
            ], 500);
        }
    }

    public function destroy($id)
    {
        try {
            $insurance = Insurance::findOrFail($id);
            if ($insurance->approval_document_path) {
                Storage::disk('public')->delete($insurance->approval_document_path);
            }
            $insurance->delete();
            
            return response()->json([
                'success' => true,
                'message' => 'Insurance record deleted successfully'
            ], 200);
        } catch (\Exception $e) {
            Log::error('Insurance deletion error:', ['error' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete insurance record'
            ], 500);
        }
    }
}