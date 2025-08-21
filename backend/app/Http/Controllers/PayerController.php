<?php

namespace App\Http\Controllers;

use App\Models\Payer;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class PayerController extends Controller
{
    public function index()
    {
        try {
            $payers = Payer::where('is_active', true)
                ->orderBy('name')
                ->get(['id', 'name', 'is_active', 'created_at', 'updated_at']);
            return response()->json(['success' => true, 'payers' => $payers], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch payers',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function show($id)
    {
        try {
            $payer = Payer::findOrFail($id);
            return response()->json([
                'success' => true,
                'payer' => $payer->only(['id', 'name', 'is_active', 'created_at', 'updated_at'])
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Payer not found',
                'error' => $e->getMessage()
            ], 404);
        }
    }

    public function store(Request $request)
    {
        try {
            if (!$request->user()->isAdmin()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized'
                ], 403);
            }

            $validator = Validator::make($request->all(), [
                'name' => 'required|string|max:255|unique:payers,name',
                'is_active' => 'boolean',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation errors',
                    'errors' => $validator->errors()
                ], 422);
            }

            $payer = Payer::create([
                'name' => $request->name,
                'is_active' => $request->is_active ?? true,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Payer created successfully',
                'payer' => $payer->only(['id', 'name', 'is_active', 'created_at', 'updated_at'])
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to create payer',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function update(Request $request, $id)
    {
        try {
            if (!$request->user()->isAdmin()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized'
                ], 403);
            }

            $validator = Validator::make($request->all(), [
                'name' => 'required|string|max:255|unique:payers,name,' . $id,
                'is_active' => 'boolean',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation errors',
                    'errors' => $validator->errors()
                ], 422);
            }

            $payer = Payer::findOrFail($id);
            $payer->update([
                'name' => $request->name,
                'is_active' => $request->is_active ?? $payer->is_active,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Payer updated successfully',
                'payer' => $payer->only(['id', 'name', 'is_active', 'created_at', 'updated_at'])
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update payer',
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

            $payer = Payer::findOrFail($id);
            $payer->delete();

            return response()->json([
                'success' => true,
                'message' => 'Payer deleted successfully'
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete payer',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function activate(Request $request)
    {
        try {
            if (!$request->user()->isAdmin()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized'
                ], 403);
            }

            $validator = Validator::make($request->all(), [
                'id' => 'required|exists:payers,id'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation errors',
                    'errors' => $validator->errors()
                ], 422);
            }

            $payer = Payer::findOrFail($request->id);
            $payer->update(['is_active' => true]);

            return response()->json([
                'success' => true,
                'message' => 'Payer activated successfully'
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to activate payer',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function deactivate(Request $request)
    {
        try {
            if (!$request->user()->isAdmin()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized'
                ], 403);
            }

            $validator = Validator::make($request->all(), [
                'id' => 'required|exists:payers,id'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation errors',
                    'errors' => $validator->errors()
                ], 422);
            }

            $payer = Payer::findOrFail($request->id);
            $payer->update(['is_active' => false]);

            return response()->json([
                'success' => true,
                'message' => 'Payer deactivated successfully'
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to deactivate payer',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}