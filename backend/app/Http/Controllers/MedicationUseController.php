<?php

namespace App\Http\Controllers;

use App\Models\MedicationUse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;

class MedicationUseController extends Controller
{
    /**
     * Display a listing of medication uses.
     */
    public function index(Request $request)
    {
        try {
            Log::info('Fetching medication uses for onboarding:', ['onboarding_id' => $request->query('onboarding_id')]);
            
            $validator = Validator::make($request->all(), [
                'onboarding_id' => 'required|exists:onboardings,id'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'error' => 'Validation failed',
                    'messages' => $validator->errors()
                ], 422);
            }

            $onboardingId = $request->query('onboarding_id');
            
            $medicationUses = MedicationUse::with(['medication' => function($query) {
                    $query->select('id', 'item_name', 'generic_name');
                }])
                ->where('onboarding_id', $onboardingId)
                ->get();

            Log::info('Found medication uses:', ['count' => $medicationUses->count()]);
                
            return response()->json([
                'success' => true,
                'data' => $medicationUses
            ]);

        } catch (\Exception $e) {
            Log::error('Error fetching medication uses: ' . $e->getMessage());
            return response()->json([
                'error' => 'Server error',
                'message' => 'Failed to fetch medication uses'
            ], 500);
        }
    }

    /**
     * Store a newly created medication use.
     */
    public function store(Request $request)
    {
        DB::beginTransaction();
        try {
            Log::info('Storing medication use:', $request->all());
            
            $validator = Validator::make($request->all(), [
                'medication_id' => 'required|exists:medications,id',
                'onboarding_id' => 'required|exists:onboardings,id',
                'days_supplied' => 'required|integer|min:1|max:365',
                'no_pills_dispensed' => 'required|integer|min:1|max:1000',
                'frequency' => 'required|in:daily,weekly,monthly',
            ]);

            if ($validator->fails()) {
                DB::rollBack();
                Log::error('Validation failed:', $validator->errors()->toArray());
                return response()->json([
                    'error' => 'Validation failed',
                    'messages' => $validator->errors()
                ], 422);
            }

            // Check if this medication already exists for this onboarding
            $existingUse = MedicationUse::where('onboarding_id', $request->onboarding_id)
                ->where('medication_id', $request->medication_id)
                ->first();

            if ($existingUse) {
                DB::rollBack();
                Log::warning('Duplicate medication use attempt:', [
                    'onboarding_id' => $request->onboarding_id,
                    'medication_id' => $request->medication_id
                ]);
                return response()->json([
                    'error' => 'Duplicate entry',
                    'message' => 'This medication is already added for this patient'
                ], 409);
            }

            $medicationUse = MedicationUse::create($request->all());
            DB::commit();

            Log::info('Medication use created successfully:', ['id' => $medicationUse->id]);
            
            return response()->json([
                'success' => true,
                'message' => 'Medication use added successfully',
                'data' => $medicationUse->load('medication')
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error storing medication use: ' . $e->getMessage());
            return response()->json([
                'error' => 'Server error',
                'message' => 'Failed to add medication use'
            ], 500);
        }
    }

    /**
     * Display the specified medication use.
     */
    public function show($id)
    {
        try {
            $medicationUse = MedicationUse::with('medication')->findOrFail($id);
            
            return response()->json([
                'success' => true,
                'data' => $medicationUse
            ]);

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'error' => 'Not found',
                'message' => 'Medication use not found'
            ], 404);
        } catch (\Exception $e) {
            Log::error('Error fetching medication use: ' . $e->getMessage());
            return response()->json([
                'error' => 'Server error',
                'message' => 'Failed to fetch medication use'
            ], 500);
        }
    }

    /**
     * Update the specified medication use.
     */
    public function update(Request $request, $id)
    {
        DB::beginTransaction();
        try {
            $medicationUse = MedicationUse::findOrFail($id);

            Log::info('Updating medication use:', ['id' => $id, 'data' => $request->all()]);

            $validator = Validator::make($request->all(), [
                'days_supplied' => 'sometimes|required|integer|min:1|max:365',
                'no_pills_dispensed' => 'sometimes|required|integer|min:1|max:1000',
                'frequency' => 'sometimes|required|in:daily,weekly,monthly',
            ]);

            if ($validator->fails()) {
                DB::rollBack();
                return response()->json([
                    'error' => 'Validation failed',
                    'messages' => $validator->errors()
                ], 422);
            }

            $medicationUse->update($request->all());
            DB::commit();

            Log::info('Medication use updated successfully:', ['id' => $medicationUse->id]);

            return response()->json([
                'success' => true,
                'message' => 'Medication use updated successfully',
                'data' => $medicationUse->load('medication')
            ]);

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            DB::rollBack();
            return response()->json([
                'error' => 'Not found',
                'message' => 'Medication use not found'
            ], 404);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error updating medication use: ' . $e->getMessage());
            return response()->json([
                'error' => 'Server error',
                'message' => 'Failed to update medication use'
            ], 500);
        }
    }

    /**
     * Remove the specified medication use.
     */
    public function destroy($id)
    {
        DB::beginTransaction();
        try {
            $medicationUse = MedicationUse::findOrFail($id);
            $medicationUse->delete();
            DB::commit();

            Log::info('Medication use deleted successfully:', ['id' => $id]);

            return response()->json([
                'success' => true,
                'message' => 'Medication use deleted successfully'
            ], 200);

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            DB::rollBack();
            return response()->json([
                'error' => 'Not found',
                'message' => 'Medication use not found'
            ], 404);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error deleting medication use: ' . $e->getMessage());
            return response()->json([
                'error' => 'Server error',
                'message' => 'Failed to delete medication use'
            ], 500);
        }
    }
}