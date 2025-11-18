<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Designation;
use Illuminate\Support\Facades\Validator;

class DesignationController extends Controller
{
    public function index()
    {
        return response()->json([
            'designations' => Designation::orderBy('id', 'desc')->get()
        ]);
    }

    public function show($id)
    {
        $designation = Designation::find($id);

        if (!$designation) {
            return response()->json(['message' => 'Designation not found'], 404);
        }

        return response()->json(['designation' => $designation]);
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255|unique:designations,name',
            'is_active' => 'boolean'
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $designation = Designation::create($request->only('name', 'is_active'));

        return response()->json([
            'message' => 'Designation created successfully',
            'designation' => $designation
        ], 201);
    }

    public function update(Request $request, $id)
    {
        $designation = Designation::find($id);
        if (!$designation) {
            return response()->json(['message' => 'Designation not found'], 404);
        }

        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255|unique:designations,name,' . $id,
            'is_active' => 'boolean'
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $designation->update($request->only('name', 'is_active'));

        return response()->json(['message' => 'Designation updated successfully']);
    }

    public function destroy($id)
    {
        $designation = Designation::find($id);

        if (!$designation) {
            return response()->json(['message' => 'Designation not found'], 404);
        }

        $designation->delete();

        return response()->json(['message' => 'Designation deleted successfully']);
    }
}
