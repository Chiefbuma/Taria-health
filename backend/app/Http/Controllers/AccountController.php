<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\Staff;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;

class AccountController extends Controller
{
    /**
     * Register a new user account
     */
    public function register(Request $request)
    {
        // Validate the request data
        $validator = Validator::make($request->all(), [
            'staff_number' => [
                'required',
                'string',
                'max:20',
                Rule::exists('staff', 'staff_number')->where('is_active', true),
            ],
            'email' => [
                'required',
                'string',
                'email',
                'max:255',
                'unique:users,email',
            ],
            'password' => 'required|string|min:6|confirmed',
        ], [
            'staff_number.exists' => 'The staff number is not found or not active in our system.',
            'email.unique' => 'This email address is already registered.',
            'password.confirmed' => 'Password confirmation does not match.',
        ]);

        // Return validation errors if any
        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Please fix the validation errors',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            // Check if staff number is already registered
            $existingUser = User::where('staff_number', $request->staff_number)->first();
            if ($existingUser) {
                return response()->json([
                    'success' => false,
                    'message' => 'Registration failed',
                    'errors' => [
                        'staff_number' => ['This staff number is already registered. Please login to your account.']
                    ]
                ], 422);
            }

            // Retrieve staff details from staff table with designation relationship
            $staff = Staff::with('designation')
                         ->where('staff_number', $request->staff_number)
                         ->where('is_active', true)
                         ->first();

            // Double-check staff exists and is active (should already be validated but being safe)
            if (!$staff) {
                return response()->json([
                    'success' => false,
                    'message' => 'Registration failed',
                    'errors' => [
                        'staff_number' => ['Staff record not found or inactive. Please contact HR.']
                    ]
                ], 422);
            }

            // Verify staff email matches the provided email (additional security)
            if ($staff->personal_email && $staff->personal_email !== $request->email) {
                return response()->json([
                    'success' => false,
                    'message' => 'Registration failed',
                    'errors' => [
                        'email' => ['Email does not match the registered staff email. Please use your official staff email.']
                    ]
                ], 422);
            }

            // Create the new user account
            $user = User::create([
                'staff_number' => $request->staff_number,
                'email' => $request->email,
                'password' => Hash::make($request->password),
                'role' => 'disbursement', // Default role for new registrations
                'is_active' => true,
                'full_name' => $staff->full_name,
                'designation' => $staff->designation->name ?? 'N/A', // Get designation name from relationship
                'business_unit' => $staff->business_unit,
                'personal_email' => $staff->personal_email,
                'mobile' => $staff->mobile,
                'date_of_joining' => $staff->date_of_joining,
            ]);

            // Generate authentication token
            $token = $user->createToken('auth_token')->plainTextToken;

            // Load staff relationship for response
            $user->load(['staff.designation']);

            // Log successful registration
            Log::info('New user registration successful', [
                'staff_number' => $user->staff_number,
                'email' => $user->email,
                'user_id' => $user->id,
                'role' => $user->role
            ]);

            // Return success response
            return response()->json([
                'success' => true,
                'message' => 'Registration successful! Welcome to Taria Health.',
                'user' => [
                    'id' => $user->id,
                    'email' => $user->email,
                    'staff_number' => $user->staff_number,
                    'role' => $user->role,
                    'is_active' => $user->is_active,
                    'full_name' => $user->full_name,
                    'designation' => $user->designation,
                    'designation_id' => $staff->designation_id, // Include designation_id
                    'business_unit' => $user->business_unit,
                    'personal_email' => $user->personal_email,
                    'mobile' => $user->mobile,
                    'date_of_joining' => $user->date_of_joining,
                    'staff_details' => $user->staff
                ],
                'token' => $token,
            ], 201);

        } catch (\Exception $e) {
            // Log detailed error information
            Log::error('User registration failed', [
                'staff_number' => $request->staff_number,
                'email' => $request->email,
                'error_message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            // Return generic error message (don't expose system details to users)
            return response()->json([
                'success' => false,
                'message' => 'Registration failed due to a system error. Please try again or contact support.',
                'error' => 'Internal server error'
            ], 500);
        }
    }

    /**
     * Log in a user
     */
    public function login(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'staff_number' => 'required|string|max:20',
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
            $user = User::where('staff_number', $request->staff_number)->first();

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

            $token = $user->createToken('auth_token')->plainTextToken;

            // Include staff and designation details in login response
            $user->load(['staff.designation']);

            // Get designation from relationship if available
            $designation = $user->staff->designation->name ?? $user->designation;

            return response()->json([
                'success' => true,
                'message' => 'Login successful',
                'user' => [
                    'id' => $user->id,
                    'email' => $user->email,
                    'staff_number' => $user->staff_number,
                    'role' => $user->role,
                    'is_active' => $user->is_active,
                    'full_name' => $user->full_name,
                    'designation' => $designation,
                    'designation_id' => $user->staff->designation_id ?? null,
                    'business_unit' => $user->business_unit,
                    'personal_email' => $user->personal_email,
                    'mobile' => $user->mobile,
                    'date_of_joining' => $user->date_of_joining,
                    'staff_details' => $user->staff
                ],
                'token' => $token,
            ], 200);
        } catch (\Exception $e) {
            Log::error('Login error: ' . $e->getMessage(), [
                'request' => $request->all(),
                'trace' => $e->getTraceAsString(),
            ]);
            return response()->json([
                'success' => false,
                'message' => 'Login failed',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get authenticated user with staff and designation details
     */
    public function getUser(Request $request)
    {
        try {
            // Load user with staff and designation relationship
            $user = $request->user()->load(['staff.designation']);
            
            // Get designation from relationship if available
            $designation = $user->staff->designation->name ?? $user->designation;

            $userData = [
                'id' => $user->id,
                'email' => $user->email,
                'staff_number' => $user->staff_number,
                'role' => $user->role,
                'is_active' => $user->is_active,
                'full_name' => $user->full_name,
                'designation' => $designation,
                'designation_id' => $user->staff->designation_id ?? null,
                'business_unit' => $user->business_unit,
                'personal_email' => $user->personal_email,
                'mobile' => $user->mobile,
                'date_of_joining' => $user->date_of_joining,
                'staff_details' => $user->staff
            ];

            return response()->json([
                'success' => true,
                'user' => $userData
            ], 200);
        } catch (\Exception $e) {
            Log::error('Get user error: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString(),
            ]);
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch user',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Log out a user
     */
    public function logout(Request $request)
    {
        try {
            $request->user()->currentAccessToken()->delete();

            return response()->json([
                'success' => true,
                'message' => 'Logout successful'
            ], 200);
        } catch (\Exception $e) {
            Log::error('Logout error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Logout failed'
            ], 500);
        }
    }

    /**
     * Update user profile
     */
    public function updateProfile(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'email' => 'sometimes|required|email|unique:users,email,' . $request->user()->id,
            'mobile' => 'sometimes|required|string|max:20',
            'personal_email' => 'sometimes|required|email',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation errors',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $user = $request->user();
            $user->update($request->only(['email', 'mobile', 'personal_email']));

            // Reload relationships
            $user->load(['staff.designation']);

            return response()->json([
                'success' => true,
                'message' => 'Profile updated successfully',
                'user' => [
                    'id' => $user->id,
                    'email' => $user->email,
                    'staff_number' => $user->staff_number,
                    'role' => $user->role,
                    'is_active' => $user->is_active,
                    'full_name' => $user->full_name,
                    'designation' => $user->staff->designation->name ?? $user->designation,
                    'designation_id' => $user->staff->designation_id ?? null,
                    'business_unit' => $user->business_unit,
                    'personal_email' => $user->personal_email,
                    'mobile' => $user->mobile,
                    'date_of_joining' => $user->date_of_joining,
                    'staff_details' => $user->staff
                ]
            ], 200);

        } catch (\Exception $e) {
            Log::error('Update profile error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to update profile',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Change password
     */
    public function changePassword(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'current_password' => 'required|string',
            'new_password' => 'required|string|min:6|confirmed',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation errors',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $user = $request->user();

            // Check current password
            if (!Hash::check($request->current_password, $user->password)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Current password is incorrect'
                ], 422);
            }

            // Update password
            $user->update([
                'password' => Hash::make($request->new_password)
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Password changed successfully'
            ], 200);

        } catch (\Exception $e) {
            Log::error('Change password error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to change password',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}