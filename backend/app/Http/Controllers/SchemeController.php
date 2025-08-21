<?php

namespace App\Http\Controllers;

use App\Models\Scheme;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class SchemeController extends Controller
{
    public function index()
    {
        try {
            $schemes = Scheme::all();
            return response()->json(['schemes' => $schemes], 200);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Failed to fetch schemes'], 500);
        }
    }

    public function activeSchemes()
    {
        try {
            $schemes = Scheme::whereNull('deleted_at')->get();
            return response()->json(['schemes' => $schemes], 200);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Failed to fetch active schemes'], 500);
        }
    }

    public function show($id)
    {
        try {
            $scheme = Scheme::findOrFail($id);
            return response()->json(['scheme' => $scheme], 200);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Scheme not found'], 404);
        }
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        try {
            $scheme = Scheme::create($request->only('name'));
            return response()->json(['message' => 'Scheme created successfully', 'scheme' => $scheme], 201);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Failed to create scheme'], 500);
        }
    }

    public function update(Request $request, $id)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        try {
            $scheme = Scheme::findOrFail($id);
            $scheme->update($request->only('name'));
            return response()->json(['message' => 'Scheme updated successfully', 'scheme' => $scheme], 200);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Failed to update scheme'], 500);
        }
    }

    public function destroy($id)
    {
        try {
            $scheme = Scheme::findOrFail($id);
            $scheme->delete();
            return response()->json(['message' => 'Scheme deleted successfully'], 200);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Failed to delete scheme'], 500);
        }
    }
}