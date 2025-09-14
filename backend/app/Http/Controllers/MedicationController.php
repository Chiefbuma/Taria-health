<?php

namespace App\Http\Controllers;

use App\Models\Medication;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Database\QueryException;

class MedicationController extends Controller
{
    /**
     * Display a listing of all medications.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function index(Request $request)
    {
        try {
            $medications = Medication::all();
            return response()->json(['medications' => $medications], 200);
        } catch (\Exception $e) {
            \Log::error('Failed to fetch medications: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString(),
            ]);
            return response()->json(['message' => 'Failed to fetch medications'], 500);
        }
    }

    /**
     * Store a newly created medication in storage.
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'item_name' => 'required|string|max:255',
            'description' => 'nullable|string|max:1000',
            'dosage' => 'nullable|string|max:255',
            'frequency' => ['nullable', Rule::in(['daily', 'twice_daily', 'weekly', 'as_needed'])],
            'is_active' => 'required|boolean',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        try {
            $medication = Medication::create(
                $request->only(['item_name', 'description', 'dosage', 'frequency', 'is_active'])
            );
            return response()->json(['message' => 'Medication created successfully', 'medication' => $medication], 201);
        } catch (QueryException $e) {
            \Log::error('Database error creating medication: ' . $e->getMessage(), [
                'input' => $request->all(),
                'trace' => $e->getTraceAsString(),
            ]);
            return response()->json(['message' => 'Database error: Unable to create medication'], 500);
        } catch (\Exception $e) {
            \Log::error('Failed to create medication: ' . $e->getMessage(), [
                'input' => $request->all(),
                'trace' => $e->getTraceAsString(),
            ]);
            return response()->json(['message' => 'Failed to create medication'], 500);
        }
    }

    /**
     * Display the specified medication.
     *
     * @param int $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function show($id)
    {
        try {
            $medication = Medication::findOrFail($id);
            return response()->json(['medication' => $medication], 200);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json(['message' => 'Medication not found'], 404);
        } catch (\Exception $e) {
            \Log::error('Failed to fetch medication: ' . $e->getMessage(), [
                'medication_id' => $id,
                'trace' => $e->getTraceAsString(),
            ]);
            return response()->json(['message' => 'Failed to fetch medication'], 500);
        }
    }

    /**
     * Update the specified medication in storage.
     *
     * @param Request $request
     * @param int $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function update(Request $request, $id)
    {
        $validator = Validator::make($request->all(), [
            'item_name' => 'required|string|max:255',
            'description' => 'nullable|string|max:1000',
            'dosage' => 'nullable|string|max:255',
            'frequency' => ['nullable', Rule::in(['daily', 'twice_daily', 'weekly', 'as_needed'])],
            'is_active' => 'required|boolean',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        try {
            $medication = Medication::findOrFail($id);
            $medication->update($request->only(['item_name', 'description', 'dosage', 'frequency', 'is_active']));
            return response()->json(['message' => 'Medication updated successfully', 'medication' => $medication], 200);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json(['message' => 'Medication not found'], 404);
        } catch (QueryException $e) {
            \Log::error('Database error updating medication: ' . $e->getMessage(), [
                'medication_id' => $id,
                'input' => $request->all(),
                'trace' => $e->getTraceAsString(),
            ]);
            return response()->json(['message' => 'Database error: Unable to update medication'], 500);
        } catch (\Exception $e) {
            \Log::error('Failed to update medication: ' . $e->getMessage(), [
                'medication_id' => $id,
                'input' => $request->all(),
                'trace' => $e->getTraceAsString(),
            ]);
            return response()->json(['message' => 'Failed to update medication'], 500);
        }
    }

    /**
     * Remove the specified medication from storage (soft delete).
     *
     * @param int $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function destroy($id)
    {
        try {
            $medication = Medication::findOrFail($id);
            $medication->delete();
            return response()->json(['message' => 'Medication deleted successfully'], 200);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json(['message' => 'Medication not found'], 404);
        } catch (\Exception $e) {
            \Log::error('Failed to delete medication: ' . $e->getMessage(), [
                'medication_id' => $id,
                'trace' => $e->getTraceAsString(),
            ]);
            return response()->json(['message' => 'Failed to delete medication'], 500);
        }
    }

    /**
     * Get all medications (public endpoint).
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function getMedications()
    {
        try {
            $medications = Medication::all();
            return response()->json(['medications' => $medications], 200);
        } catch (\Exception $e) {
            \Log::error('Failed to fetch all medications: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString(),
            ]);
            return response()->json(['message' => 'Failed to fetch medications'], 500);
        }
    }
}
