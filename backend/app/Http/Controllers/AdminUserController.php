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
        return $user && $user->isActive() && (
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
                Log::warning('Unauthorized attempt to fetch users:', [
                    'user_id' => $request->user()?->id,
                    'role' => $request->user()?->role
                ]);
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized'
                ], 403);
            }

            $user = $request->user();
            $query = User::select('id', 'email', 'phone', 'role', 'payer_id', 'is_active', 'created_at', 'updated_at');

            // Restrict payers to only see users with matching payer_id
            if ($user->role === User::ROLE_PAYER) {
                if (!$user->payer_id) {
                    Log::warning('Payer has no payer_id:', ['user_id' => $user->id]);
                    return response()->json([
                        'success' => false,
                        'message' => 'Payer ID not set for this user'
                    ], 403);
                }
                $query->where('payer_id', $user->payer_id);
            }

            $users = $query->get();

            Log::info('Users retrieved successfully:', [
                'user_id' => $user->id,
                'role' => $user->role,
                'count' => $users->count(),
                'payer_id' => $user->payer_id ?? null
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Users retrieved successfully',
                'users' => $users
            ], 200);
        } catch (\Exception $e) {
            Log::error('Error fetching users:', [
                'user_id' => $request->user()?->id,
                'role' => $request->user()?->role,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
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
                Log::warning('Unauthorized attempt to fetch user:', [
                    'user_id' => $request->user()?->id,
                    'role' => $request->user()?->role,
                    'target_user_id' => $id
                ]);
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized'
                ], 403);
            }

            $user = $request->user();
            $targetUser = User::select('id', 'email', 'phone', 'role', 'payer_id', 'is_active', 'created_at', 'updated_at')
                ->findOrFail($id);

            // Restrict payers to only see users with matching payer_id
            if ($user->role === User::ROLE_PAYER) {
                if (!$user->payer_id) {
                    Log::warning('Payer has no payer_id:', ['user_id' => $user->id]);
                    return response()->json([
                        'success' => false,
                        'message' => 'Payer ID not set for this user'
                    ], 403);
                }
                if ($targetUser->payer_id !== $user->payer_id) {
                    Log::warning('Payer unauthorized to view user:', [
                        'user_id' => $user->id,
                        'target_user_id' => $id,
                        'payer_id' => $user->payer_id
                    ]);
                    return response()->json([
                        'success' => false,
                        'message' => 'Unauthorized to view this user'
                    ], 403);
                }
            }

            Log::info('User retrieved successfully:', [
                'user_id' => $user->id,
                'role' => $user->role,
                'target_user_id' => $id,
                'payer_id' => $user->payer_id ?? null
            ]);

            return response()->json([
                'success' => true,
                'message' => 'User retrieved successfully',
                'user' => $targetUser
            ], 200);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            Log::error('User not found:', ['user_id' => $id]);
            return response()->json([
                'success' => false,
                'message' => 'User not found'
            ], 404);
        } catch (\Exception $e) {
            Log::error('Error fetching user:', [
                'user_id' => $request->user()?->id,
                'role' => $request->user()?->role,
                'target_user_id' => $id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch user',
                'error' => env('APP_DEBUG') ? $e->getMessage() : null
            ], 500);
        }
    }

    /**
     * Create a new user (admin, payer).
     */
    public function store(Request $request)
    {
        $user = $request->user();
        Log::debug('store debug:', [
            'user_exists' => !empty($user),
            'user_id' => $user?->id,
            'role' => $user?->role,
            'is_active' => $user?->isActive(),
            'payer_id' => $user?->payer_id,
        ]);

        if (!$user || !$user->isActive()) {
            Log::warning('Unauthorized attempt to create user:', [
                'user_id' => $user?->id,
                'role' => $user?->role
            ]);
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized'
            ], 401);
        }

        $allowedRoles = [User::ROLE_ADMIN, User::ROLE_PAYER];
        if (!in_array($user->role, $allowedRoles)) {
            Log::warning('Unauthorized role for creating user:', [
                'user_id' => $user->id,
                'role' => $user->role
            ]);
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized'
            ], 403);
        }

        $rules = [
            'email' => 'required|string|email|max:255|unique:users,email',
            'phone' => 'required|string|regex:/^0[0-9]{9}$/|unique:users,phone',
            'password' => 'required|string|min:6|confirmed',
            'role' => 'required|in:user,navigator,claims',
            'is_active' => 'sometimes|boolean',
        ];

        if ($user->role === User::ROLE_ADMIN && $request->role === 'payer') {
            $rules['payer_id'] = 'required|exists:payers,id';
        } elseif ($request->role === 'user') {
            $rules['payer_id'] = 'required|exists:payers,id';
        }

        $validator = Validator::make($request->all(), $rules, [
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
            $payerId = $request->role === 'user' ? $request->payer_id : ($request->role === 'payer' && $user->isAdmin() ? $request->payer_id : null);
            if ($user->role === User::ROLE_PAYER) {
                if (!$user->payer_id) {
                    Log::warning('Payer has no payer_id:', ['user_id' => $user->id]);
                    return response()->json([
                        'success' => false,
                        'message' => 'Payer ID not set for this user'
                    ], 403);
                }
                $payerId = $user->payer_id; // Force payer_id for ROLE_PAYER
            }

            $newUser = DB::transaction(function () use ($request, $payerId) {
                return User::create([
                    'email' => $request->email,
                    'phone' => $request->phone,
                    'password' => Hash::make($request->password),
                    'role' => $request->role,
                    'payer_id' => $payerId,
                    'is_active' => $request->is_active ?? true,
                ]);
            });

            Log::info('User created successfully:', [
                'user_id' => $user->id,
                'role' => $user->role,
                'new_user_id' => $newUser->id,
                'new_user_role' => $newUser->role,
                'payer_id' => $payerId
            ]);

            return response()->json([
                'success' => true,
                'message' => 'User created successfully',
                'user' => $newUser->only(['id', 'email', 'phone', 'role', 'payer_id', 'is_active'])
            ], 201);
        } catch (\Exception $e) {
            Log::error('User creation error:', [
                'user_id' => $user?->id,
                'role' => $user?->role,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return response()->json([
                'success' => false,
                'message' => 'User creation failed',
                'error' => env('APP_DEBUG') ? $e->getMessage() : null
            ], 500);
        }
    }

    /**
     * Update a user (admin, payer).
     */
    public function update(Request $request, $id)
    {
        $user = $request->user();
        Log::debug('update debug:', [
            'user_exists' => !empty($user),
            'user_id' => $user?->id,
            'role' => $user?->role,
            'is_active' => $user?->isActive(),
            'payer_id' => $user?->payer_id,
            'target_user_id' => $id
        ]);

        if (!$user || !$user->isActive()) {
            Log::warning('Unauthorized attempt to update user:', [
                'user_id' => $user?->id,
                'role' => $user?->role,
                'target_user_id' => $id
            ]);
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized'
            ], 401);
        }

        $allowedRoles = [User::ROLE_ADMIN, User::ROLE_PAYER];
        if (!in_array($user->role, $allowedRoles)) {
            Log::warning('Unauthorized role for updating user:', [
                'user_id' => $user->id,
                'role' => $user->role,
                'target_user_id' => $id
            ]);
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized'
            ], 403);
        }

        $rules = [
            'email' => 'sometimes|string|email|max:255|unique:users,email,' . $id,
            'phone' => 'sometimes|string|regex:/^0[0-9]{9}$/|unique:users,phone,' . $id,
            'role' => 'sometimes|in:user,navigator,claims',
            'is_active' => 'sometimes|boolean',
            'password' => 'sometimes|string|min:6|confirmed',
        ];

        if ($user->role === User::ROLE_ADMIN && $request->role === 'payer') {
            $rules['payer_id'] = 'required|exists:payers,id';
        } elseif ($request->has('role') && $request->role === 'user') {
            $rules['payer_id'] = 'required|exists:payers,id';
        }

        $validator = Validator::make($request->all(), $rules, [
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
            $targetUser = User::findOrFail($id);

            if ($user->id === $targetUser->id) {
                Log::warning('Attempt to update own account:', ['user_id' => $user->id]);
                return response()->json([
                    'success' => false,
                    'message' => 'You cannot update your own account'
                ], 403);
            }

            if ($user->role === User::ROLE_PAYER) {
                if (!$user->payer_id) {
                    Log::warning('Payer has no payer_id:', ['user_id' => $user->id]);
                    return response()->json([
                        'success' => false,
                        'message' => 'Payer ID not set for this user'
                    ], 403);
                }
                if ($targetUser->payer_id !== $user->payer_id) {
                    Log::warning('Payer unauthorized to update user:', [
                        'user_id' => $user->id,
                        'target_user_id' => $id,
                        'payer_id' => $user->payer_id
                    ]);
                    return response()->json([
                        'success' => false,
                        'message' => 'Unauthorized to update this user'
                    ], 403);
                }
            }

            $data = $request->only(['email', 'phone', 'role', 'is_active']);
            if ($request->has('password')) {
                $data['password'] = Hash::make($request->password);
            }
            if ($request->has('role')) {
                $data['payer_id'] = $request->role === 'user' ? $request->payer_id : ($request->role === 'payer' && $user->isAdmin() ? $request->payer_id : null);
                if ($user->role === User::ROLE_PAYER && $request->role === 'user') {
                    $data['payer_id'] = $user->payer_id; // Force payer_id for ROLE_PAYER
                }
            }

            $targetUser->update($data);

            Log::info('User updated successfully:', [
                'user_id' => $user->id,
                'role' => $user->role,
                'target_user_id' => $targetUser->id,
                'target_user_role' => $targetUser->role,
                'payer_id' => $data['payer_id'] ?? null
            ]);

            return response()->json([
                'success' => true,
                'message' => 'User updated successfully',
                'user' => $targetUser->only(['id', 'email', 'phone', 'role', 'payer_id', 'is_active'])
            ], 200);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            Log::error('User not found:', ['user_id' => $id]);
            return response()->json([
                'success' => false,
                'message' => 'User not found'
            ], 404);
        } catch (\Exception $e) {
            Log::error('User update error:', [
                'user_id' => $user?->id,
                'role' => $user?->role,
                'target_user_id' => $id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return response()->json([
                'success' => false,
                'message' => 'Failed to update user',
                'error' => env('APP_DEBUG') ? $e->getMessage() : null
            ], 500);
        }
    }

    /**
     * Delete a user (admin, payer).
     */
    public function destroy(Request $request, $id)
    {
        $user = $request->user();
        Log::debug('destroy debug:', [
            'user_exists' => !empty($user),
            'user_id' => $user?->id,
            'role' => $user?->role,
            'is_active' => $user?->isActive(),
            'payer_id' => $user?->payer_id,
            'target_user_id' => $id
        ]);

        if (!$user || !$user->isActive()) {
            Log::warning('Unauthorized attempt to delete user:', [
                'user_id' => $user?->id,
                'role' => $user?->role,
                'target_user_id' => $id
            ]);
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized'
            ], 401);
        }

        $allowedRoles = [User::ROLE_ADMIN, User::ROLE_PAYER];
        if (!in_array($user->role, $allowedRoles)) {
            Log::warning('Unauthorized role for deleting user:', [
                'user_id' => $user->id,
                'role' => $user->role,
                'target_user_id' => $id
            ]);
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized'
            ], 403);
        }

        DB::beginTransaction();
        try {
            $targetUser = User::findOrFail($id);

            if ($user->id === $targetUser->id) {
                Log::warning('Attempt to delete own account:', ['user_id' => $user->id]);
                DB::rollBack();
                return response()->json([
                    'success' => false,
                    'message' => 'You cannot delete your own account'
                ], 403);
            }

            if ($user->role === User::ROLE_PAYER) {
                if (!$user->payer_id) {
                    Log::warning('Payer has no payer_id:', ['user_id' => $user->id]);
                    DB::rollBack();
                    return response()->json([
                        'success' => false,
                        'message' => 'Payer ID not set for this user'
                    ], 403);
                }
                if ($targetUser->payer_id !== $user->payer_id) {
                    Log::warning('Payer unauthorized to delete user:', [
                        'user_id' => $user->id,
                        'target_user_id' => $id,
                        'payer_id' => $user->payer_id
                    ]);
                    DB::rollBack();
                    return response()->json([
                        'success' => false,
                        'message' => 'Unauthorized to delete this user'
                    ], 403);
                }
            }

            $targetUser->delete();
            DB::commit();

            Log::info('User deleted successfully:', [
                'user_id' => $user->id,
                'role' => $user->role,
                'target_user_id' => $id,
                'payer_id' => $user->payer_id ?? null
            ]);

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
            Log::error('User deletion error:', [
                'user_id' => $user?->id,
                'role' => $user?->role,
                'target_user_id' => $id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete user',
                'error' => env('APP_DEBUG') ? $e->getMessage() : null
            ], 500);
        }
    }

    /**
     * Get available payers for dropdown.
     */
    public function getPayers(Request $request)
    {
        try {
            $user = $request->user();
            Log::debug('getPayers debug:', [
                'user_exists' => !empty($user),
                'user_id' => $user?->id,
                'role' => $user?->role,
                'is_active' => $user?->isActive(),
            ]);

            if (!$user || !$user->isActive()) {
                Log::warning('Unauthorized attempt to fetch payers:', [
                    'user_id' => $user?->id,
                    'role' => $user?->role
                ]);
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized'
                ], 401);
            }

            $payers = Payer::select('id', 'name')->get();

            Log::info('Payers retrieved successfully:', [
                'user_id' => $user->id,
                'role' => $user->role,
                'count' => $payers->count(),
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Payers retrieved successfully',
                'payers' => $payers
            ], 200);
        } catch (\Exception $e) {
            Log::error('Error fetching payers:', [
                'user_id' => $request->user()?->id,
                'role' => $request->user()?->role,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch payers',
                'error' => env('APP_DEBUG') ? $e->getMessage() : null
            ], 500);
        }
    }
}
