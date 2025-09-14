<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;

class AccountController extends Controller
{
    /**
     * Register a new user.
     */
    public function register(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|string|email|max:255|unique:users',
            'phone' => 'required|string|max:20|unique:users',
            'password' => 'required|string|min:6|confirmed',
            'role' => 'sometimes|in:admin,user,navigator,payer,guest,claims',
            'payer_id' => 'sometimes|exists:payers,id', // Add validation for payer_id
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
                'role' => $request->role ?? User::ROLE_USER,
                'payer_id' => $request->payer_id, // Include payer_id
                'is_active' => true,
            ]);

            $token = $user->createToken('auth_token')->plainTextToken;

            return response()->json([
                'success' => true,
                'message' => 'Registration successful',
                'user' => [
                    'id' => $user->id,
                    'email' => $user->email,
                    'phone' => $user->phone,
                    'role' => $user->role,
                    'payer_id' => $user->payer_id,
                    'payer' => $user->payer ? [
                        'id' => $user->payer->id,
                        'name' => $user->payer->name,
                    ] : null,
                    'is_active' => $user->is_active,
                ],
                'token' => $token,
            ], 201);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Registration failed',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Log in a user.
     */
    public function login(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'phone' => 'required|string|max:20',
            'password' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation errors',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $user = User::where('phone', $request->phone)->first()->load('payer');

            if (!$user || !Hash::check($request->password, $user->password)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid credentials'
                ], 401);
            }

            if (!$user->is_active) {
                return response()->json([
                    'success' => false,
                    'message' => 'Your account has been deactivated. Please contact admin.'
                ], 403);
            }

            if ($user->isPayer() && !$user->payer_id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Payer ID is missing for this account. Please contact admin.'
                ], 403);
            }

            $token = $user->createToken('auth_token')->plainTextToken;

            return response()->json([
                'success' => true,
                'message' => 'Login successful',
                'user' => [
                    'id' => $user->id,
                    'email' => $user->email,
                    'phone' => $user->phone,
                    'role' => $user->role,
                    'payer_id' => $user->payer_id,
                    'payer' => $user->payer ? [
                        'id' => $user->payer->id,
                        'name' => $user->payer->name,
                    ] : null,
                    'is_active' => $user->is_active,
                ],
                'token' => $token,
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Login failed',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get all users (admin or payer).
     */
    public function getUsers(Request $request)
    {
        try {
            $user = $request->user();
            $query = User::select('id', 'email', 'phone', 'role', 'payer_id', 'is_active', 'created_at', 'updated_at');

            if ($user->isPayer()) {
                if (!$user->payer_id) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Payer ID is missing for this account'
                    ], 403);
                }
                $payerId = $request->query('payer_id', $user->payer_id);
                if ($payerId != $user->payer_id) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Unauthorized: Invalid payer ID'
                    ], 403);
                }
                $query->where('payer_id', $user->payer_id);
            } elseif (!$user->isAdmin()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized'
                ], 403);
            }

            $users = $query->get();

            return response()->json([
                'success' => true,
                'users' => $users
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch users',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update user (admin or payer).
     */
    public function updateUser(Request $request, $id)
    {
        try {
            $user = $request->user();
            $targetUser = User::findOrFail($id);

            if ($user->isPayer()) {
                if (!$user->payer_id) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Payer ID is missing for this account'
                    ], 403);
                }
                if ($targetUser->payer_id != $user->payer_id) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Unauthorized: Cannot modify users from other payers'
                    ], 403);
                }
            } elseif (!$user->isAdmin()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized'
                ], 403);
            }

            $validator = Validator::make($request->all(), [
                'email' => 'sometimes|string|email|max:255|unique:users,email,' . $id,
                'phone' => 'sometimes|string|max:20|unique:users,phone,' . $id,
                'role' => 'sometimes|in:admin,user,navigator,payer,guest,claims',
                'payer_id' => 'sometimes|exists:payers,id',
                'is_active' => 'sometimes|boolean',
                'password' => 'sometimes|string|min:6|confirmed',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation errors',
                    'errors' => $validator->errors()
                ], 422);
            }

            if ($targetUser->id === $user->id) {
                return response()->json([
                    'success' => false,
                    'message' => 'You cannot modify your own account'
                ], 403);
            }

            $data = $request->only(['email', 'phone', 'role', 'payer_id', 'is_active']);
            if ($request->has('password')) {
                $data['password'] = Hash::make($request->password);
            }
            $targetUser->update($data);

            return response()->json([
                'success' => true,
                'message' => 'User updated successfully',
                'user' => [
                    'id' => $targetUser->id,
                    'email' => $targetUser->email,
                    'phone' => $targetUser->phone,
                    'role' => $targetUser->role,
                    'payer_id' => $targetUser->payer_id,
                    'is_active' => $targetUser->is_active,
                ]
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
     * Delete a user (admin or payer).
     */
    public function delete(Request $request)
    {
        try {
            $user = $request->user();
            $validator = Validator::make($request->all(), [
                'id' => 'required|integer|exists:users,id',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation errors',
                    'errors' => $validator->errors()
                ], 422);
            }

            $targetUser = User::findOrFail($request->id);

            if ($user->isPayer()) {
                if (!$user->payer_id) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Payer ID is missing for this account'
                    ], 403);
                }
                if ($targetUser->payer_id != $user->payer_id) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Unauthorized: Cannot delete users from other payers'
                    ], 403);
                }
            } elseif (!$user->isAdmin()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized'
                ], 403);
            }

            if ($targetUser->id === $user->id) {
                return response()->json([
                    'success' => false,
                    'message' => 'You cannot delete your own account'
                ], 403);
            }

            $targetUser->delete();

            return response()->json([
                'success' => true,
                'message' => 'User deleted successfully'
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete user',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get authenticated user.
     */
    public function getUser(Request $request)
    {
        try {
            $user = $request->user()->load('payer');
            if ($user->isPayer() && !$user->payer_id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Payer ID is missing for this account'
                ], 403);
            }

            return response()->json([
                'success' => true,
                'user' => [
                    'id' => $user->id,
                    'email' => $user->email,
                    'phone' => $user->phone,
                    'role' => $user->role,
                    'payer_id' => $user->payer_id,
                    'payer' => $user->payer ? [
                        'id' => $user->payer->id,
                        'name' => $user->payer->name,
                    ] : null,
                    'is_active' => $user->is_active,
                ]
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch user',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}