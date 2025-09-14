<?php

namespace App\Http\Controllers;

use App\Models\Insurance;
use App\Models\Onboarding;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Database\QueryException;

class InsuranceController extends Controller
{
    /**
     * Check if user has access to insurance records.
     */
    private function hasAccess(Request $request)
    {
        $user = $request->user();
        if (!$user || !$user->isActive()) {
            Log::warning('Access denied', [
                'user_id' => $user?->id,
                'role' => $user?->role,
                'active' => $user?->isActive(),
            ]);
            return false;
        }
        $allowedRoles = ['admin', User::ROLE_NAVIGATOR, User::ROLE_PAYER, User::ROLE_USER, User::ROLE_CLAIMS];
        return in_array($user->role, $allowedRoles);
    }

    /**
     * Fetch insurance records based on user role.
     */
    public function index(Request $request)
    {
        try {
            $user = $request->user();
            Log::debug('Insurance index debug:', [
                'user_exists' => !empty($user),
                'user_id' => $user?->id,
                'role' => $user?->role,
                'is_active' => $user?->isActive(),
            ]);

            if (!$user) {
                Log::warning('No authenticated user found for insurance access');
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized'
                ], 403);
            }

            if (!$user->isActive()) {
                Log::warning('Inactive user attempted to access insurance:', [
                    'user_id' => $user->id,
                    'role' => $user->role
                ]);
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized'
                ], 403);
            }

            $allowedRoles = [
                User::ROLE_ADMIN,
                User::ROLE_NAVIGATOR,
                User::ROLE_PAYER,
                User::ROLE_USER,
                User::ROLE_CLAIMS
            ];

            if (!in_array($user->role, $allowedRoles)) {
                Log::warning('Unauthorized role for fetching insurance records:', [
                    'user_id' => $user->id,
                    'role' => $user->role
                ]);
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized'
                ], 403);
            }

            $query = Insurance::query()->with('onboarding');

            // Restrict payers to only see insurance records associated with their payer_id
            if ($user->role === User::ROLE_PAYER) {
                if (!$user->payer_id) {
                    Log::warning('Payer has no payer_id:', ['user_id' => $user->id]);
                    return response()->json([
                        'success' => false,
                        'message' => 'Payer ID not set for this user'
                    ], 403);
                }
                $query->whereHas('onboarding', function ($q) use ($user) {
                    $q->where('payer_id', $user->payer_id);
                }, '>=', 0); // Allow records with no onboarding
            }
            // Restrict users to only see their own insurance records
            elseif ($user->role === User::ROLE_USER) {
                $query->where('user_id', $user->id);
            }
            // Admins and Claims roles see all insurance records, no additional filtering
            // Navigator role also sees all records, as per existing logic

            $insurances = $query->get();
            Log::info('Insurance records retrieved successfully:', [
                'user_id' => $user->id,
                'role' => $user->role,
                'count' => $insurances->count(),
                'records' => $insurances->toArray(),
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Insurance records retrieved successfully',
                'data' => $insurances,
            ], 200);
        } catch (\Exception $e) {
            Log::error('Error fetching insurance records:', [
                'user_id' => $request->user()?->id,
                'role' => $request->user()?->role,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch insurance records',
                'error' => env('APP_DEBUG') ? $e->getMessage() : null
            ], 500);
        }
    }

    /**
     * Show a single insurance record.
     */
    public function show($id)
    {
        try {
            $insurance = Insurance::with('onboarding')->findOrFail($id);
            return response()->json([
                'success' => true,
                'data' => $insurance,
            ], 200);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json(['success' => false, 'message' => 'Insurance record not found'], 404);
        } catch (\Exception $e) {
            Log::error('Error fetching insurance record', [
                'id' => $id,
                'error' => $e->getMessage(),
            ]);
            return response()->json(['success' => false, 'message' => 'Failed to fetch insurance record'], 500);
        }
    }

    /**
     * Store a new insurance record.
     */
    public function store(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'user_id' => 'required|exists:users,id',
                'insurance_provider' => 'required|string|max:255',
                'policy_number' => 'required|string|max:255|unique:insurance,policy_number',
                'claim_amount' => 'nullable|numeric|min:0',
                'is_approved' => 'nullable|in:pending,approved,rejected',
                'payment_date' => 'nullable|date',
                'approval_document' => 'nullable|file|mimes:pdf,jpg,png|max:10240',
            ]);

            if ($validator->fails()) {
                return response()->json(['success' => false, 'errors' => $validator->errors()], 422);
            }

            $data = $request->only([
                'user_id',
                'insurance_provider',
                'policy_number',
                'claim_amount',
                'is_approved',
                'payment_date',
            ]);

            if ($request->hasFile('approval_document')) {
                $file = $request->file('approval_document');
                $data['approval_document_path'] = $file->store('insurance_docs', 'public');
                $data['approval_document_name'] = $file->getClientOriginalName();
            }

            $data['is_approved'] = $data['is_approved'] ?? 'pending';

            $insurance = Insurance::create($data);

            return response()->json([
                'success' => true,
                'data' => $insurance,
                'message' => 'Insurance record created successfully',
            ], 201);
        } catch (QueryException $e) {
            Log::error('Error creating insurance record', [
                'error' => $e->getMessage(),
                'request_data' => $request->all(),
            ]);
            if ($e->getCode() === '23000' && str_contains($e->getMessage(), 'insurance_policy_number_unique')) {
                return response()->json([
                    'success' => false,
                    'message' => 'Policy number already exists',
                ], 422);
            }
            return response()->json([
                'success' => false,
                'message' => 'Failed to create insurance record',
                'error' => $e->getMessage(),
            ], 500);
        } catch (\Exception $e) {
            Log::error('Error creating insurance record', [
                'error' => $e->getMessage(),
                'request_data' => $request->all(),
            ]);
            return response()->json([
                'success' => false,
                'message' => 'Failed to create insurance record',
            ], 500);
        }
    }

    /**
     * Update an existing insurance record.
     */
    public function update(Request $request, $id)
    {
        try {
            $insurance = Insurance::findOrFail($id);
            $validator = Validator::make($request->all(), [
                'insurance_provider' => 'nullable|string|max:255',
                'policy_number' => 'nullable|string|max:255|unique:insurance,policy_number,' . $id,
                'claim_amount' => 'nullable|numeric|min:0',
                'is_approved' => 'nullable|in:pending,approved,rejected',
                'payment_date' => 'nullable|date',
                'approval_document' => 'nullable|file|mimes:pdf,jpg,png|max:10240',
            ]);

            if ($validator->fails()) {
                return response()->json(['success' => false, 'errors' => $validator->errors()], 422);
            }

            $data = $request->only([
                'insurance_provider',
                'policy_number',
                'claim_amount',
                'is_approved',
                'payment_date',
            ]);

            if ($request->hasFile('approval_document')) {
                if ($insurance->approval_document_path) {
                    Storage::disk('public')->delete($insurance->approval_document_path);
                }
                $file = $request->file('approval_document');
                $data['approval_document_path'] = $file->store('insurance_docs', 'public');
                $data['approval_document_name'] = $file->getClientOriginalName();
            }

            $insurance->update($data);
            return response()->json([
                'success' => true,
                'data' => $insurance,
                'message' => 'Insurance record updated successfully',
            ], 200);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json(['success' => false, 'message' => 'Insurance record not found'], 404);
        } catch (\Exception $e) {
            Log::error('Error updating insurance record', [
                'id' => $id,
                'error' => $e->getMessage(),
            ]);
            return response()->json([
                'success' => false,
                'message' => 'Failed to update insurance record',
            ], 500);
        }
    }

    /**
     * Delete an entire insurance record.
     */
    public function destroy($id)
    {
        try {
            $insurance = Insurance::findOrFail($id);
            if (!$this->hasAccess(request())) {
                return response()->json(['success' => false, 'message' => 'Unauthorized'], 403);
            }

            if ($insurance->approval_document_path) {
                Storage::disk('public')->delete($insurance->approval_document_path);
            }

            $insurance->delete();
            return response()->json([
                'success' => true,
                'message' => 'Insurance record deleted successfully',
            ], 200);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json(['success' => false, 'message' => 'Insurance record not found'], 404);
        } catch (\Exception $e) {
            Log::error('Error deleting insurance record', [
                'id' => $id,
                'error' => $e->getMessage(),
            ]);
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete insurance record',
            ], 500);
        }
    }

    /**
     * Delete an insurance approval document.
     */
    public function deleteDocument($id)
    {
        try {
            $insurance = Insurance::findOrFail($id);
            if (!$insurance->approval_document_path) {
                return response()->json(['success' => false, 'message' => 'No document found'], 404);
            }

            Storage::disk('public')->delete($insurance->approval_document_path);
            $insurance->update([
                'approval_document_path' => null,
                'approval_document_name' => null,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Document deleted successfully',
            ], 200);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json(['success' => false, 'message' => 'Insurance record not found'], 404);
        } catch (\Exception $e) {
            Log::error('Error deleting document', [
                'id' => $id,
                'error' => $e->getMessage(),
            ]);
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete document',
            ], 500);
        }
    }
}
