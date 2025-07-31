<?php

namespace App\Http\Controllers;

use App\Models\Medication;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class MedicationController extends Controller
{
    /**
     * Display a listing of medications.
     */
    public function index(Request $request)
    {
        try {
            $medications = Medication::select('medication_id', 'item_name', 'created_at', 'updated_at')
                ->get();

            return response()->json([
                'success' => true,
                'medications' => $medications
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch medications',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Store a newly created medication in storage.
     */
    public function store(Request $request)
    {
        try {
            // Check if user is admin
            if (!$request->user()->isAdmin()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized'
                ], 403);
            }

            $validator = Validator::make($request->all(), [
                'item_name' => 'required|string|max:255|unique:medication,item_name',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation errors',
                    'errors' => $validator->errors()
                ], 422);
            }

            $medication = Medication::create([
                'item_name' => $request->item_name,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Medication created successfully',
                'medication' => $medication->only(['medication_id', 'item_name', 'created_at', 'updated_at'])
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to create medication',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Display the specified medication.
     */
    public function show(Request $request, $id)
    {
        try {
            $medication = Medication::findOrFail($id);

            return response()->json([
                'success' => true,
                'medication' => $medication->only(['medication_id', 'item_name', 'created_at', 'updated_at'])
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Medication not found',
                'error' => $e->getMessage()
            ], 404);
        }
    }

    /**
     * Update the specified medication in storage.
     */
    public function update(Request $request, $id)
    {
        try {
            // Check if user is admin
            if (!$request->user()->isAdmin()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized'
                ], 403);
            }

            $validator = Validator::make($request->all(), [
                'item_name' => 'required|string|max:255|unique:medication,item_name,' . $id . ',medication_id',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation errors',
                    'errors' => $validator->errors()
                ], 422);
            }

            $medication = Medication::findOrFail($id);
            $medication->update([
                'item_name' => $request->item_name,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Medication updated successfully',
                'medication' => $medication->only(['medication_id', 'item_name', 'created_at', 'updated_at'])
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update medication',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Remove the specified medication from storage (soft delete).
     */
    public function destroy(Request $request, $id)
    {
        try {
            // Check if user is admin
            if (!$request->user()->isAdmin()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized'
                ], 403);
            }

            $medication = Medication::findOrFail($id);
            $medication->delete();

            return response()->json([
                'success' => true,
                'message' => 'Medication deleted successfully'
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete medication',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
