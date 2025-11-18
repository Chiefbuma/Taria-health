<?php
// app/Http/Controllers/Api/StaffController.php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Staff;
use App\Models\User;
use Illuminate\Support\Facades\Validator;

class StaffController extends Controller
{
    public function index()
    {
        // Include designation relationship
        $staff = Staff::with('designation')->orderBy('id', 'desc')->get();
        
        return response()->json([
            'success' => true,
            'staff' => $staff
        ]);
    }

    public function show($identifier)
    {
        $staff = Staff::with(['user', 'designation'])
                    ->where('staff_number', $identifier)
                    ->orWhere('id', $identifier)
                    ->first();

        if (!$staff) {
            return response()->json([
                'success' => false,
                'message' => 'Staff member not found'
            ], 404);
        }

        return response()->json([
            'success' => true,
            'staff' => $staff
        ]);
    }

    public function getByStaffNumber($staffNumber)
    {
        $staff = Staff::with(['user', 'designation'])
                    ->where('staff_number', $staffNumber)
                    ->first();

        if (!$staff) {
            return response()->json([
                'success' => false,
                'message' => 'Staff member not found'
            ], 404);
        }

        return response()->json([
            'success' => true,
            'staff' => $staff
        ]);
    }

    // ✅ Updated store() method using designation_id relationship
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'staff_number' => 'required|string|max:20|unique:staff,staff_number',
            'full_name' => 'required|string|max:255',
            'date_of_joining' => 'required|date',
            'designation_id' => 'nullable|exists:designations,id', // ✅ Fetch designation from table
            'personal_email' => 'required|email|unique:staff,personal_email',
            'business_unit' => 'required|string|max:255',
            'mobile' => 'required|string|max:20',
            'is_active' => 'sometimes|boolean'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $staffData = $request->all();

            // Ensure is_active is set
            if (!isset($staffData['is_active'])) {
                $staffData['is_active'] = true;
            }

            $staff = Staff::create($staffData);

            // Load the designation relationship
            $staff->load('designation');

            return response()->json([
                'success' => true,
                'message' => 'Staff member created successfully',
                'staff' => $staff
            ], 201);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to create staff member',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function update(Request $request, $id)
    {
        $staff = Staff::find($id);
        if (!$staff) {
            return response()->json([
                'success' => false,
                'message' => 'Staff member not found'
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'staff_number' => 'required|string|max:20|unique:staff,staff_number,' . $id,
            'full_name' => 'required|string|max:255',
            'date_of_joining' => 'required|date',
            'designation_id' => 'nullable|exists:designations,id',
            'personal_email' => 'required|email|unique:staff,personal_email,' . $id,
            'business_unit' => 'required|string|max:255',
            'mobile' => 'required|string|max:20',
            'is_active' => 'sometimes|boolean'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $staff->update($request->all());
        $staff->load('designation');

        return response()->json([
            'success' => true,
            'message' => 'Staff member updated successfully',
            'staff' => $staff
        ]);
    }

    public function destroy($id)
    {
        $staff = Staff::find($id);

        if (!$staff) {
            return response()->json([
                'success' => false,
                'message' => 'Staff member not found'
            ], 404);
        }

        // Check if staff has associated user
        $user = User::where('staff_number', $staff->staff_number)->first();
        if ($user) {
            return response()->json([
                'success' => false,
                'message' => 'Cannot delete staff member with associated user account'
            ], 422);
        }

        $staff->delete();

        return response()->json([
            'success' => true,
            'message' => 'Staff member deleted successfully'
        ]);
    }

    public function activate($id)
    {
        $staff = Staff::find($id);
        
        if (!$staff) {
            return response()->json([
                'success' => false,
                'message' => 'Staff member not found'
            ], 404);
        }

        $staff->update(['is_active' => true]);

        return response()->json([
            'success' => true,
            'message' => 'Staff member activated successfully',
            'staff' => $staff
        ]);
    }

    public function deactivate($id)
    {
        $staff = Staff::find($id);
        
        if (!$staff) {
            return response()->json([
                'success' => false,
                'message' => 'Staff member not found'
            ], 404);
        }

        $staff->update(['is_active' => false]);

        return response()->json([
            'success' => true,
            'message' => 'Staff member deactivated successfully',
            'staff' => $staff
        ]);
    }

    public function activeStaff()
    {
        $staff = Staff::with('designation')
                    ->where('is_active', true)
                    ->orderBy('id', 'desc')
                    ->get();
        
        return response()->json([
            'success' => true,
            'staff' => $staff
        ]);
    }

    public function inactiveStaff()
    {
        $staff = Staff::with('designation')
                    ->where('is_active', false)
                    ->orderBy('id', 'desc')
                    ->get();
        
        return response()->json([
            'success' => true,
            'staff' => $staff
        ]);
    }
}
