<?php

namespace App\Http\Controllers;

use App\Models\Clinic;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class ClinicController extends Controller
{
    public function index()
{
    try {
        $clinics = Clinic::where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name', 'code', 'is_active']);
        return response()->json([
            'success' => true,
            'clinics' => $clinics
        ]);
    } catch (\Exception $e) {
        return response()->json([
            'success' => false,
            'message' => 'Failed to fetch clinics',
            'error' => $e->getMessage()
        ], 500);
    }
}

    public function show($id)
    {
        try {
            $clinic = Clinic::findOrFail($id);
            return response()->json([
                'success' => true,
                'clinic' => $clinic
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Clinic not found',
                'error' => $e->getMessage()
            ], 404);
        }
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'code' => 'required|string|max:50|unique:clinics,code',
            'is_active' => 'boolean',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $clinic = Clinic::create($request->all());
            return response()->json([
                'success' => true,
                'message' => 'Clinic created successfully',
                'clinic' => $clinic
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to create clinic',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function update(Request $request, $id)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'code' => 'required|string|max:50|unique:clinics,code,' . $id,
            'is_active' => 'boolean',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $clinic = Clinic::findOrFail($id);
            $clinic->update($request->all());
            return response()->json([
                'success' => true,
                'message' => 'Clinic updated successfully',
                'clinic' => $clinic
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update clinic',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function destroy(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'id' => 'required|exists:clinics,id'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $clinic = Clinic::findOrFail($request->id);
            $clinic->delete();
            return response()->json([
                'success' => true,
                'message' => 'Clinic deleted successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete clinic',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function activate(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'id' => 'required|exists:clinics,id'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $clinic = Clinic::findOrFail($request->id);
            $clinic->update(['is_active' => true]);
            return response()->json([
                'success' => true,
                'message' => 'Clinic activated successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to activate clinic',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function deactivate(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'id' => 'required|exists:clinics,id'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $clinic = Clinic::findOrFail($request->id);
            $clinic->update(['is_active' => false]);
            return response()->json([
                'success' => true,
                'message' => 'Clinic deactivated successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to deactivate clinic',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}