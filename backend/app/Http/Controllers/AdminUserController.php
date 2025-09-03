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
     * Check if the user has access to user records (admin, navigator, payer).
     */
    private function hasAccess(Request $request)
    {
        $user = $request->user();
        return $user && (
            $user->isAdmin() ||
            $user->role === User::ROLE_NAVIGATOR ||
            $user->role === User::ROLE_PAYER
        );
    }

    /**
     * Get all users (admin, navigator, payer).
     */
    public function index(Request $request)
    {
        try {
            if (!$this->hasAccess($request)) {
                Log::warning('Unauthorized attempt to fetch users:', ['user_id' => $request->user()->id]);
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized'
                ], 403);
            }

            $user = $request->user();
            $query = User::select('id', 'email', 'phone', 'role', 'payer_id', 'is_active', 'created_at', 'updated_at');

            // Restrict payers to only see users with matching payer_id
            if ($user->role === User::ROLE_PAYER) {
                $query->where('payer_id', $user->payer_id);
            }

            $users = $query->get();

            Log::info('Users retrieved successfully:', ['user_id' => $user->id, 'count' => $users->count()]);

            return response()->json([
                'success' => true,
                'message' => 'Users retrieved successfully',
                'users' => $users
            ], 200);
        } catch (\Exception $e) {
            Log::error('Error fetching users:', ['error' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch users',
                'error' => env('APP_DEBUG') ? $e->getMessage() : null
            ], 500);
        }
    }

    /**
     * Get a single user (admin, navigator, payer).
     */
    public function show(Request $request, $id)
    {
        try {
            if (!$this->hasAccess($request)) {
                Log::warning('Unauthorized attempt to fetch user:', ['user_id' => $request->user()->id, 'target_user_id' => $id]);
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized'
                ], 403);
            }

            $user = User::select('id', 'email', 'phone', 'role', 'payer_id', 'is_active', 'created_at', 'updated_at')
                ->findOrFail($id);

            // Restrict payers to only see users with matching payer_id
            if ($request->user()->role === User::ROLE_PAYER && $user->payer_id !== $request->user()->payer_id) {
                Log::warning('Payer unauthorized to view user:', ['user_id' => $request->user()->id, 'target_user_id' => $id]);
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized to view this user'
                ], 403);
            }

            Log::info('User retrieved successfully:', ['user_id' => $request->user()->id, 'target_user_id' => $id]);

            return response()->json([
                'success' => true,
                'message' => 'User retrieved successfully',
                'user' => $user
            ], 200);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            Log::error('User not found:', ['user_id' => $id]);
            return response()->json([
                'success' => false,
                'message' => 'User not found'
            ], 404);
        } catch (\Exception $e) {
            Log::error('Error fetching user:', ['user_id' => $id, 'error' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch user',
                'error' => env('APP_DEBUG') ? $e->getMessage() : null
            ], 500);
        }
    }

    /**
     * Create a new user (admin only).
     */
    public function store(Request $request)
    {
        if (!$request->user()->isAdmin()) {
            Log::warning('Unauthorized attempt to create user:', ['user_id' => $request->user()->id]);
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized'
            ], 403);
        }

        $validator = Validator::make($request->all(), [
            'email' => 'required|string|email|max:255|unique:users,email',
            'phone' => 'required|string|regex:/^0[0-9]{9}$/|unique:users,phone',
            'password' => 'required|string|min:6|confirmed',
            'role' => 'required|in:admin,user,navigator,payer,guest,claims',
            'payer_id' => 'required_if:role,payer|exists:payers,id|nullable',
            'is_active' => 'sometimes|boolean',
        ], [
            'phone.regex' => 'Phone number must be 10 digits starting with 0 (e.g., 0712345678).'
        ]);

        if ($validator->fails()) {
            Log::error('User creation validation failed:', $validator->errors()->toArray());
            return response()->json([
                'success' => false,
                'message' => 'Validation errors',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $user = DB::transaction(function () use ($request) {
                return User::create([
                    'email' => $request->email,
                    'phone' => $request->phone,
                    'password' => Hash::make($request->password),
                    'role' => $request->role,
                    'payer_id' => $request->role === User::ROLE_PAYER ? $request->payer_id : null,
                    'is_active' => $request->is_active ?? true,
                ]);
            });

            Log::info('User created successfully:', ['user_id' => $user->id, 'email' => $user->email]);

            return response()->json([
                'success' => true,
                'message' => 'User created successfully',
                'user' => $user->only(['id', 'email', 'phone', 'role', 'payer_id', 'is_active'])
            ], 201);
        } catch (\Exception $e) {
            Log::error('User creation error:', ['error' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);
            return response()->json([
                'success' => false,
                'message' => 'User creation failed',
                'error' => env('APP_DEBUG') ? $e->getMessage() : null
            ], 500);
        }
    }

    /**
     * Update a user (admin only).
     */
    public function update(Request $request, $id)
    {
        if (!$request->user()->isAdmin()) {
            Log::warning('Unauthorized attempt to update user:', ['user_id' => $request->user()->id, 'target_user_id' => $id]);
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized'
            ], 403);
        }

        $validator = Validator::make($request->all(), [
            'email' => 'sometimes|string|email|max:255|unique:users,email,' . $id,
            'phone' => 'sometimes|string|regex:/^0[0-9]{9}$/|unique:users,phone,' . $id,
            'role' => 'sometimes|in:admin,user,navigator,payer,guest,claims',
            'payer_id' => 'required_if:role,payer|exists:payers,id|nullable',
            'is_active' => 'sometimes|boolean',
            'password' => 'sometimes|string|min:6|confirmed',
        ], [
            'phone.regex' => 'Phone number must be 10 digits starting with 0 (e.g., 0712345678).'
        ]);

        if ($validator->fails()) {
            Log::error('User update validation failed:', $validator->errors()->toArray());
            return response()->json([
                'success' => false,
                'message' => 'Validation errors',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $user = User::findOrFail($id);

            if ($user->id === $request->user()->id) {
                Log::warning('Attempt to update own account:', ['user_id' => $user->id]);
                return response()->json([
                    'success' => false,
                    'message' => 'You cannot update your own account'
                ], 403);
            }

            $data = $request->only(['email', 'phone', 'role', 'is_active']);
            if ($request->has('role')) {
                $data['payer_id'] = $request->role === User::ROLE_PAYER ? $request->payer_id : null;
            }
            if ($request->has('password')) {
                $data['password'] = Hash::make($request->password);
            }

            $user->update($data);

            Log::info('User updated successfully:', ['user_id' => $user->id, 'email' => $user->email]);

            return response()->json([
                'success' => true,
                'message' => 'User updated successfully',
                'user' => $user->only(['id', 'email', 'phone', 'role', 'payer_id', 'is_active'])
            ], 200);
        } catch (\Exception $e) {
            Log::error('User update error:', ['error' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);
            return response()->json([
                'success' => false,
                'message' => 'Failed to update user',
                'error' => env('APP_DEBUG') ? $e->getMessage() : null
            ], 500);
        }
    }

    /**
     * Delete a user (admin only).
     */
    public function destroy(Request $request, $id)
    {
        if (!$request->user()->isAdmin()) {
            Log::warning('Unauthorized attempt to delete user:', ['user_id' => $request->user()->id, 'target_user_id' => $id]);
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized'
            ], 403);
        }

        DB::beginTransaction();
        try {
            $user = User::findOrFail($id);

            if ($user->id === $request->user()->id) {
                Log::warning('Attempt to delete own account:', ['user_id' => $user->id]);
                DB::rollBack();
                return response()->json([
                    'success' => false,
                    'message' => 'You cannot delete your own account'
                ], 403);
            }

            $user->delete();
            DB::commit();

            Log::info('User deleted successfully:', ['user_id' => $id]);

            return response()->json([
                'success' => true,
                'message' => 'User deleted successfully'
            ], 200);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            DB::rollBack();
            Log::error('User not found:', ['user_id' => $id]);
            return response()->json([
                'success' => false,
                'message' => 'User not found'
            ], 404);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('User deletion error:', ['user_id' => $id, 'error' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete user',
                'error' => env('APP_DEBUG') ? $e->getMessage() : null
            ], 500);
        }
    }
}