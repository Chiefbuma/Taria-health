<?php

namespace App\Http\Controllers;

use App\Models\Insurance;
use App\Models\Onboarding;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Storage;

class InsuranceController extends Controller
{
    public function index()
    {
        try {
            $insurances = Insurance::all();
            return response()->json([
                'success' => true,
                'insurances' => $insurances
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch insurances',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function show($id)
    {
        try {
            $insurance = Insurance::findOrFail($id);
            return response()->json([
                'success' => true,
                'insurance' => $insurance
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Insurance record not found',
                'error' => $e->getMessage()
            ], 404);
        }
    }

    public function store(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'insurance_provider' => 'required|string|max:255',
                'policy_number' => 'required|string|max:255|unique:insurance,policy_number',
                'claim_amount' => 'nullable|numeric|min:0',
                'onboarding_id' => 'nullable|exists:onboardings,id',
                'user_id' => 'required|exists:users,id',
                'is_approved' => 'nullable|string|in:approved,pending',
                'approval_document' => 'nullable|file|mimes:pdf,jpg,png|max:10240',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation errors',
                    'errors' => $validator->errors()
                ], 422);
            }

            $data = $request->only([
                'insurance_provider',
                'policy_number',
                'claim_amount',
                'onboarding_id',
                'user_id',
                'is_approved'
            ]);
            
            $data['is_approved'] = $data['is_approved'] ?? 'pending';

            if ($request->hasFile('approval_document')) {
                $file = $request->file('approval_document');
                $fileName = time() . '_' . $file->getClientOriginalName();
                $filePath = $file->storeAs('insurance_docs', $fileName, 'public');
                $data['approval_document_path'] = $filePath;
                $data['approval_document_name'] = $file->getClientOriginalName();
            }

            $insurance = Insurance::create($data);

            if (!empty($data['onboarding_id']) && $data['is_approved'] === 'approved') {
                $onboarding = Onboarding::find($data['onboarding_id']);
                if ($onboarding) {
                    $onboarding->update([
                        'payment_status' => 'approved',
                        'payment_method' => 'insurance',
                        'insurance_id' => $insurance->id,
                        'insurance_provider' => $data['insurance_provider']
                    ]);
                }
            }

            return response()->json([
                'success' => true,
                'message' => 'Insurance created successfully',
                'insurance' => $insurance
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to create insurance',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function update(Request $request, $id)
    {
        try {
            $insurance = Insurance::findOrFail($id);

            $validator = Validator::make($request->all(), [
                'insurance_provider' => 'required|string|max:255',
                'policy_number' => 'required|string|max:255|unique:insurance,policy_number,' . $id,
                'claim_amount' => 'nullable|numeric|min:0',
                'is_approved' => 'required|string|in:approved,pending',
                'payment_date' => 'nullable|date',
                'onboarding_id' => 'nullable|exists:onboardings,id',
                'approval_document' => 'nullable|file|mimes:pdf,jpg,png|max:10240',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation errors',
                    'errors' => $validator->errors()
                ], 422);
            }

            $data = $request->only([
                'insurance_provider',
                'policy_number',
                'claim_amount',
                'onboarding_id',
                'is_approved',
                'payment_date'
            ]);

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

            $insurance->update($data);

            if (!empty($data['onboarding_id']) && $data['is_approved'] === 'approved') {
                $onboarding = $insurance->onboarding;
                if ($onboarding) {
                    $onboarding->update([
                        'payment_status' => 'approved',
                        'payment_method' => 'insurance',
                        'insurance_id' => $insurance->id,
                        'insurance_provider' => $data['insurance_provider']
                    ]);
                }
            }

            return response()->json([
                'success' => true,
                'message' => 'Insurance updated successfully',
                'insurance' => $insurance
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update insurance',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function destroy($id)
    {
        try {
            if (!request()->user()->isAdmin()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized'
                ], 403);
            }

            $insurance = Insurance::findOrFail($id);
            if ($insurance->approval_document_path) {
                Storage::disk('public')->delete($insurance->approval_document_path);
            }
            $insurance->delete();

            return response()->json([
                'success' => true,
                'message' => 'Insurance deleted successfully'
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete insurance',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function approve($id)
    {
        try {
            $insurance = Insurance::findOrFail($id);
            $insurance->update(['is_approved' => 'approved']);

            if (!empty($insurance->onboarding_id)) {
                $onboarding = Onboarding::find($insurance->onboarding_id);
                if ($onboarding) {
                    $onboarding->update(['payment_status' => 'approved']);
                }
            }

            return response()->json([
                'success' => true,
                'message' => 'Insurance approved successfully',
                'insurance' => $insurance
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to approve insurance',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}