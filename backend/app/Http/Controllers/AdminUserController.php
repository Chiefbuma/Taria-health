<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\Payer;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;

class AdminUserController extends Controller
{
    /**
     * Create a new user (admin only)
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|string|email|max:255|unique:users',
            'phone' => 'required|string|regex:/^0[0-9]{9}$/|unique:users,phone',
            'password' => 'required|string|min:6|confirmed',
            'role' => 'required|in:admin,user,navigator,payer,guest',
            'payer_id' => 'required_if:role,payer|exists:payers,id',
            'is_active' => 'sometimes|boolean',
        ], [
            'phone.regex' => 'Phone number must be 10 digits starting with 0 (e.g., 0712345678).'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation errors',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $user = User::create([
                'email' => $request->email,
                'phone' => $request->phone,
                'password' => Hash::make($request->password),
                'role' => $request->role,
                'payer_id' => $request->role === 'payer' ? $request->payer_id : null,
                'is_active' => $request->is_active ?? true,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'User created successfully',
                'user' => $user->only(['id', 'email', 'phone', 'role', 'is_active', 'payer_id'])
            ], 201);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'User creation failed',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update user (admin only)
     */
    public function update(Request $request, $id)
    {
        $validator = Validator::make($request->all(), [
            'email' => 'sometimes|string|email|max:255|unique:users,email,' . $id,
            'phone' => 'sometimes|string|regex:/^0[0-9]{9}$/|unique:users,phone,' . $id,
            'role' => 'sometimes|in:admin,user,navigator,payer,guest',
            'payer_id' => 'required_if:role,payer|exists:payers,id',
            'is_active' => 'sometimes|boolean',
            'password' => 'sometimes|string|min:6|confirmed',
        ], [
            'phone.regex' => 'Phone number must be 10 digits starting with 0 (e.g., 0712345678).'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation errors',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $user = User::findOrFail($id);

            $data = $request->only(['email', 'phone', 'role', 'is_active']);
            
            // Handle payer_id based on role
            if ($request->has('role')) {
                $data['payer_id'] = $request->role === 'payer' 
                    ? $request->payer_id 
                    : null;
            }

            if ($request->has('password')) {
                $data['password'] = Hash::make($request->password);
            }

            $user->update($data);

            return response()->json([
                'success' => true,
                'message' => 'User updated successfully',
                'user' => $user->only(['id', 'email', 'phone', 'role', 'is_active', 'payer_id'])
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update user',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Delete a user (admin only)
     */
    public function destroy($id)
    {
        DB::beginTransaction();
        try {
            Log::info('Attempting to delete user:', ['id' => $id]);

            if (!request()->user()->isAdmin()) {
                DB::rollBack();
                Log::warning('Unauthorized attempt to delete user:', ['id' => $id]);
                return response()->json([
                    'error' => 'Unauthorized',
                    'message' => 'You do not have permission to delete users'
                ], 403);
            }

            $user = User::findOrFail($id);

            if ($user->id === request()->user()->id) {
                DB::rollBack();
                Log::warning('Attempt to delete own account:', ['id' => $id]);
                return response()->json([
                    'error' => 'Forbidden',
                    'message' => 'You cannot delete your own account'
                ], 403);
            }

            $user->delete();
            DB::commit();

            Log::info('User deleted successfully:', ['id' => $id]);

            return response()->json([
                'success' => true,
                'message' => 'User deleted successfully'
            ], 200);

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            DB::rollBack();
            Log::error('User not found:', ['id' => $id]);
            return response()->json([
                'error' => 'Not found',
                'message' => 'User not found'
            ], 404);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error deleting user: ' . $e->getMessage(), ['id' => $id]);
            return response()->json([
                'error' => 'Server error',
                'message' => 'Failed to delete user'
            ], 500);
        }
    }
}