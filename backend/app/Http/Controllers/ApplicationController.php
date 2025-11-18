<?php
// app/Http/Controllers/ApplicationController.php

namespace App\Http\Controllers;

use App\Models\Application;
use App\Models\Document;
use App\Models\ApprovalLog;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ApplicationController extends Controller
{
    /**
     * Get all applications for the authenticated user
     */
    public function index(Request $request)
    {
        try {
            $user = $request->user();
            
            \Log::info('Fetching applications for user:', ['user_id' => $user->id, 'role' => $user->role]);
            
            $query = Application::with(['user.staff', 'documents']);
            
            if ($user->role === 'user') {
                $query->where('user_id', $user->id);
            } else {
                // Approvers see applications at their level
                $query->where('current_approval_level', $user->role)
                      ->where('status', 'pending');
            }

            // Filter by status
            if ($request->has('status') && $request->status !== 'All') {
                $query->where('status', $request->status);
            }

            // Search
            if ($request->has('search')) {
                $query->where('application_id', 'like', '%' . $request->search . '%');
            }

            $applications = $query->orderBy('created_at', 'desc')->get();
            
            \Log::info('Applications found:', ['count' => $applications->count()]);
            
            return response()->json($applications);
            
        } catch (\Exception $e) {
            \Log::error('Error fetching applications:', ['error' => $e->getMessage()]);
            return response()->json(['error' => 'Failed to fetch applications'], 500);
        }
    }

    /**
     * Get only the current user's applications
     */
    public function getUserApplications(Request $request)
    {
        try {
            $user = $request->user();
            
            $applications = Application::with(['documents', 'user.staff'])
                ->where('user_id', $user->id)
                ->orderBy('created_at', 'desc')
                ->get();
            
            return response()->json($applications);
            
        } catch (\Exception $e) {
            \Log::error('Error fetching user applications:', ['error' => $e->getMessage()]);
            return response()->json(['error' => 'Failed to fetch user applications'], 500);
        }
    }

    /**
     * Get pending applications for approvers
     */
    public function getPendingApplications(Request $request)
    {
        try {
            $user = $request->user();
            
            // Only approvers can see pending applications
            if ($user->role === 'user') {
                return response()->json(['error' => 'Unauthorized'], 403);
            }

            $applications = Application::with(['user.staff', 'documents'])
                ->where('current_approval_level', $user->role)
                ->where('status', 'pending')
                ->orderBy('created_at', 'desc')
                ->get();

            return response()->json($applications);
            
        } catch (\Exception $e) {
            \Log::error('Error fetching pending applications:', ['error' => $e->getMessage()]);
            return response()->json(['error' => 'Failed to fetch pending applications'], 500);
        }
    }

    /**
     * Get dashboard statistics
     */
    public function getDashboardStats(Request $request)
    {
        try {
            $user = $request->user();
            
            if ($user->role === 'user') {
                $total = Application::where('user_id', $user->id)->count();
                $pending = Application::where('user_id', $user->id)->where('status', 'pending')->count();
                $approved = Application::where('user_id', $user->id)->where('status', 'approved')->count();
                $rejected = Application::where('user_id', $user->id)->where('status', 'rejected')->count();
            } else {
                $total = Application::count();
                $pending = Application::where('status', 'pending')->count();
                $approved = Application::where('status', 'approved')->count();
                $rejected = Application::where('status', 'rejected')->count();
            }

            return response()->json([
                'total_applications' => $total,
                'pending_applications' => $pending,
                'approved_applications' => $approved,
                'rejected_applications' => $rejected,
            ]);
            
        } catch (\Exception $e) {
            \Log::error('Error fetching dashboard stats:', ['error' => $e->getMessage()]);
            return response()->json(['error' => 'Failed to fetch dashboard statistics'], 500);
        }
    }

    /**
     * Get recent applications
     */
    public function getRecentApplications(Request $request)
    {
        try {
            $user = $request->user();
            
            $query = Application::with(['user.staff']);
            
            if ($user->role === 'user') {
                $query->where('user_id', $user->id);
            }

            $applications = $query->orderBy('created_at', 'desc')
                ->limit(10)
                ->get();

            return response()->json($applications);
            
        } catch (\Exception $e) {
            \Log::error('Error fetching recent applications:', ['error' => $e->getMessage()]);
            return response()->json(['error' => 'Failed to fetch recent applications'], 500);
        }
    }

    /**
     * Create a new application
     */
    public function store(Request $request)
    {
        try {
            \Log::info('Application store request received');
            
            $request->validate([
                'application_type' => 'required|in:childbirth,wedding,bereavement,logistics',
                'comment' => 'nullable|string',
                'documents' => 'sometimes|array',
                'documents.*.file' => 'sometimes|file|mimes:pdf,png,jpg,jpeg|max:10240',
                'documents.*.document_type' => 'sometimes|string'
            ]);

            $user = $request->user();

            // Generate application ID
            $applicationId = 'APP-' . date('Ymd') . '-' . Str::random(6);

            $application = Application::create([
                'application_id' => $applicationId,
                'application_type' => $request->application_type,
                'user_id' => $user->id,
                'comment' => $request->comment,
                'status' => 'pending',
                'current_approval_level' => 'chair',
                'receipt_confirmation' => 'pending' // Default value
            ]);

            // Handle document uploads if provided
            if ($request->has('documents')) {
                foreach ($request->documents as $doc) {
                    if (isset($doc['file'])) {
                        $file = $doc['file'];
                        $fileName = time() . '_' . Str::random(10) . '.' . $file->getClientOriginalExtension();
                        $filePath = $file->storeAs('documents', $fileName, 'public');

                        Document::create([
                            'application_id' => $application->id,
                            'user_id' => $user->id,
                            'document_name' => $file->getClientOriginalName(),
                            'file_path' => $filePath,
                            'file_type' => $file->getClientOriginalExtension(),
                            'file_size' => $file->getSize(),
                            'document_type' => $doc['document_type'] ?? 'document'
                        ]);
                    }
                }
            }

            \Log::info('Application created successfully:', ['id' => $application->id]);

            return response()->json([
                'message' => 'Application created successfully',
                'application' => $application->load(['documents', 'user.staff'])
            ], 201);

        } catch (\Exception $e) {
            \Log::error('Error creating application:', ['error' => $e->getMessage()]);
            return response()->json(['error' => 'Failed to create application: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Get a single application
     */
    public function show($id)
    {
        try {
            $application = Application::with([
                'documents', 
                'user.staff',
                'approvalLogs.approver.staff'
            ])->find($id);
            
            if (!$application) {
                return response()->json(['error' => 'Application not found'], 404);
            }

            return response()->json($application);
            
        } catch (\Exception $e) {
            \Log::error('Error fetching application:', ['error' => $e->getMessage()]);
            return response()->json(['error' => 'Failed to fetch application'], 500);
        }
    }

    /**
     * Update an application
     */
    public function update(Request $request, $id)
    {
        try {
            $application = Application::find($id);
            
            if (!$application) {
                return response()->json(['error' => 'Application not found'], 404);
            }

            $request->validate([
                'comment' => 'nullable|string',
                'status' => 'sometimes|in:pending,approved,rejected,needs_information'
            ]);

            $application->update($request->only(['comment', 'status']));

            return response()->json([
                'message' => 'Application updated successfully',
                'application' => $application->load(['documents', 'user.staff'])
            ]);
            
        } catch (\Exception $e) {
            \Log::error('Error updating application:', ['error' => $e->getMessage()]);
            return response()->json(['error' => 'Failed to update application'], 500);
        }
    }

    /**
     * Delete an application
     */
    public function destroy($id)
    {
        try {
            $application = Application::find($id);
            
            if (!$application) {
                return response()->json(['error' => 'Application not found'], 404);
            }

            // Delete associated documents and files
            foreach ($application->documents as $document) {
                if (Storage::disk('public')->exists($document->file_path)) {
                    Storage::disk('public')->delete($document->file_path);
                }
                $document->delete();
            }
            
            // Delete approval logs
            $application->approvalLogs()->delete();
            
            $application->delete();

            return response()->json(['message' => 'Application deleted successfully']);
            
        } catch (\Exception $e) {
            \Log::error('Error deleting application:', ['error' => $e->getMessage()]);
            return response()->json(['error' => 'Failed to delete application'], 500);
        }
    }

    /**
     * Update application status (approval workflow)
     */
    public function updateStatus(Request $request, $id)
    {
        try {
            $application = Application::find($id);
            
            if (!$application) {
                return response()->json(['error' => 'Application not found'], 404);
            }

            $request->validate([
                'action' => 'required|in:approved,rejected,requested_changes',
                'comments' => 'nullable|string',
                // Treasurer specific fields - no validation for testing
                'amount' => 'nullable',
                'cheque_number' => 'nullable',
                'disbursement_status' => 'nullable|in:pending,processed,completed'
            ]);

            $user = $request->user();
            
            // Check if user is authorized to approve at this level
            if ($application->current_approval_level !== $user->role) {
                return response()->json(['error' => 'Not authorized to approve this application'], 403);
            }

            // Create approval log
            ApprovalLog::create([
                'application_id' => $application->id,
                'approver_id' => $user->id,
                'approval_level' => $user->role,
                'action' => $request->action,
                'comments' => $request->comments
            ]);

            // Update application based on action
            if ($request->action === 'approved') {
                // If treasurer is approving, update financial fields
                if ($user->role === 'treasurer') {
                    $updateData = [];
                    
                    if ($request->has('amount') && $request->amount !== null) {
                        // Convert amount to integer
                        $updateData['amount'] = (int)$request->amount;
                    }
                    if ($request->has('cheque_number') && $request->cheque_number !== null) {
                        $updateData['cheque_number'] = $request->cheque_number;
                    }
                    if ($request->has('disbursement_status') && $request->disbursement_status !== null) {
                        $updateData['disbursement_status'] = $request->disbursement_status;
                    }
                    
                    if (!empty($updateData)) {
                        $application->update($updateData);
                    }
                }
                
                if ($this->canMoveToNextLevel($application)) {
                    $application->current_approval_level = $this->getNextApprovalLevel($application);
                } else {
                    $application->status = 'approved';
                    // Set disbursement status to pending if not already set
                    if (empty($application->disbursement_status)) {
                        $application->disbursement_status = 'pending';
                    }
                }
            } elseif ($request->action === 'rejected') {
                $application->status = 'rejected';
            } else {
                $application->status = 'needs_information';
            }

            $application->save();

            return response()->json([
                'message' => 'Application status updated successfully',
                'application' => $application->load(['approvalLogs.approver.staff'])
            ]);
            
        } catch (\Exception $e) {
            \Log::error('Error updating application status:', ['error' => $e->getMessage()]);
            return response()->json(['error' => 'Failed to update application status: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Update disbursement confirmation (for disbursement officers)
     */
    public function updateDisbursementConfirmation(Request $request, Application $application)
    {
        try {
            $request->validate([
                'disbursement_confirmed' => 'required|boolean',
                'disbursement_comment' => 'nullable|string|max:500'
            ]);

            // Check if user has permission to update disbursement
            $user = $request->user();
            if (!$user->hasRole('disbursement')) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized. Only disbursement officers can update this status.'
                ], 403);
            }

            // Update the application
            $updateData = [
                'disbursement_confirmed' => $request->disbursement_confirmed,
                'disbursement_comment' => $request->disbursement_comment
            ];

            if ($request->disbursement_confirmed) {
                $updateData['disbursement_confirmed_at'] = now();
                $updateData['disbursement_confirmed_by'] = $user->id;
                $updateData['disbursement_status'] = 'processed';
            } else {
                $updateData['disbursement_confirmed_at'] = null;
                $updateData['disbursement_confirmed_by'] = null;
                $updateData['disbursement_status'] = 'pending';
            }

            $application->update($updateData);

            // Create approval log entry
            ApprovalLog::create([
                'application_id' => $application->id,
                'approver_id' => $user->id,
                'approval_level' => 'disbursement',
                'action' => $request->disbursement_confirmed ? 'disbursement_confirmed' : 'disbursement_rejected',
                'comments' => $request->disbursement_comment
            ]);

            // Reload the application with relationships
            $application->load(['user.staff.designation', 'documents', 'approvalLogs.approver.staff']);

            \Log::info('Disbursement confirmation updated', [
                'application_id' => $application->id,
                'user_id' => $user->id,
                'confirmed' => $request->disbursement_confirmed
            ]);

            return response()->json([
                'success' => true,
                'message' => $request->disbursement_confirmed 
                    ? 'Disbursement confirmed successfully' 
                    : 'Disbursement confirmation removed',
                'application' => $application
            ]);

        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            \Log::error('Error updating disbursement confirmation', [
                'application_id' => $application->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to update disbursement confirmation'
            ], 500);
        }
    }

    /**
     * Update receipt confirmation (for original applicant)
     */
   /**
 * Update receipt confirmation (for original applicant)
 */
/**
 * Update receipt confirmation (for original applicant)
 */
/**
 * Update receipt confirmation (for original applicant)
 */
public function updateReceiptConfirmation(Request $request, $id)
{
    try {
        $application = Application::with(['user', 'approvalLogs'])->find($id);
        
        if (!$application) {
            return response()->json(['error' => 'Application not found'], 404);
        }

        $user = $request->user();
        
        \Log::info('Receipt confirmation attempt', [
            'application_id' => $id,
            'user_id' => $user->id,
            'app_user_id' => $application->user_id,
            'current_status' => $application->status,
            'current_level' => $application->current_approval_level,
            'disbursement_status' => $application->disbursement_status
        ]);
        
        // Check if user is the original applicant
        if ($application->user_id !== $user->id) {
            return response()->json(['error' => 'Not authorized to update this application'], 403);
        }

        // More flexible validation for receipt confirmation
        $isReadyForReceipt = $application->status === 'pending' && 
                            $application->current_approval_level === 'disbursement';
        
        $isInDisbursementStage = $application->disbursement_status === 'processed' || 
                                $application->disbursement_status === 'pending';
        
        if (!$isReadyForReceipt && !$isInDisbursementStage) {
            return response()->json([
                'error' => 'Application is not ready for receipt confirmation',
                'details' => [
                    'current_status' => $application->status,
                    'current_approval_level' => $application->current_approval_level,
                    'disbursement_status' => $application->disbursement_status,
                    'required_status' => 'approved',
                    'required_approval_level' => 'disbursement'
                ]
            ], 400);
        }

        $request->validate([
            'receipt_confirmation' => 'required|in:pending,received'
        ]);

        // Update receipt confirmation
        $application->receipt_confirmation = $request->receipt_confirmation;
        
        // If receipt is received, complete the disbursement
        if ($request->receipt_confirmation === 'received') {
            $application->disbursement_status = 'completed';
            $application->status = 'approved'; // Mark as fully completed
            
            // Use 'approved' action instead of 'receipt_confirmed'
            ApprovalLog::create([
                'application_id' => $application->id,
                'approver_id' => $user->id,
                'approval_level' => 'disbursement',
                'action' => 'approved', // Using existing action value
                'comments' => 'Receipt confirmed by applicant - Funds received successfully'
            ]);
            
            \Log::info('Receipt confirmed and disbursement completed', [
                'application_id' => $application->id,
                'user_id' => $user->id
            ]);
            
            // REMOVED: toast.success('Receipt confirmed successfully! Application completed.');
        } else {
            // If setting back to pending, reset disbursement status
            $application->disbursement_status = 'processed';
            
            // Use 'requested_changes' for reset action
            ApprovalLog::create([
                'application_id' => $application->id,
                'approver_id' => $user->id,
                'approval_level' => 'disbursement',
                'action' => 'requested_changes', // Using existing action value
                'comments' => 'Receipt confirmation reset to pending - Awaiting funds receipt'
            ]);
            
            \Log::info('Receipt confirmation reset to pending', [
                'application_id' => $application->id,
                'user_id' => $user->id
            ]);
            
            // REMOVED: toast.success('Receipt status reset to pending.');
        }
        
        $application->save();

        // Reload with relationships
        $application->load(['user.staff', 'approvalLogs.approver.staff']);

        return response()->json([
            'message' => 'Receipt confirmation updated successfully',
            'application' => $application
        ]);
        
    } catch (\Illuminate\Validation\ValidationException $e) {
        \Log::error('Validation error in receipt confirmation:', [
            'errors' => $e->errors(),
            'application_id' => $id
        ]);
        return response()->json(['error' => 'Validation failed', 'errors' => $e->errors()], 422);
    } catch (\Exception $e) {
        \Log::error('Error updating receipt confirmation:', [
            'error' => $e->getMessage(),
            'trace' => $e->getTraceAsString(),
            'application_id' => $id,
            'user_id' => $request->user()->id ?? 'unknown'
        ]);
        return response()->json(['error' => 'Failed to update receipt confirmation: ' . $e->getMessage()], 500);
    }
}
    /**
     * Check if application can move to next approval level
     */
    private function canMoveToNextLevel($application)
    {
        $currentLevel = $application->current_approval_level;
        $levels = ['chair', 'treasurer', 'disbursement'];
        $currentIndex = array_search($currentLevel, $levels);
        
        return $currentIndex !== false && $currentIndex < count($levels) - 1;
    }

    /**
     * Get next approval level
     */
    private function getNextApprovalLevel($application)
    {
        $levels = ['chair', 'treasurer', 'disbursement'];
        $currentIndex = array_search($application->current_approval_level, $levels);
        
        return $levels[$currentIndex + 1] ?? null;
    }

    /**
     * Get applications for specific user (admin function)
     */
    public function getUserApplicationsById($userId)
    {
        try {
            $applications = Application::with(['documents', 'user.staff'])
                ->where('user_id', $userId)
                ->orderBy('created_at', 'desc')
                ->get();

            return response()->json($applications);
            
        } catch (\Exception $e) {
            \Log::error('Error fetching user applications by ID:', ['error' => $e->getMessage()]);
            return response()->json(['error' => 'Failed to fetch user applications'], 500);
        }
    }

    /**
     * Bulk update applications status
     */
    public function bulkUpdateStatus(Request $request)
    {
        try {
            $request->validate([
                'application_ids' => 'required|array',
                'application_ids.*' => 'exists:applications,id',
                'status' => 'required|in:pending,approved,rejected,needs_information'
            ]);

            $user = $request->user();

            Application::whereIn('id', $request->application_ids)
                ->update([
                    'status' => $request->status,
                    'current_approval_level' => $user->role
                ]);

            return response()->json(['message' => 'Applications updated successfully']);
            
        } catch (\Exception $e) {
            \Log::error('Error in bulk update:', ['error' => $e->getMessage()]);
            return response()->json(['error' => 'Failed to update applications'], 500);
        }
    }
    /**
 * Get analytics data for dashboard
 */
/**
 * Get analytics data for dashboard
 */
/**
 * Get analytics data for dashboard with proper applicant details
 */
public function getAnalyticsData(Request $request)
{
    try {
        $user = $request->user();
        
        \Log::info('Fetching analytics data for user:', [
            'user_id' => $user->id, 
            'role' => $user->role,
            'filters' => $request->all()
        ]);

        // Load applications with proper relationships
        $query = Application::with([
            'user.staff.designation',
            'documents'
        ]);

        // Apply filters if provided
        if ($request->has('month') && $request->month) {
            $query->whereYear('created_at', substr($request->month, 0, 4))
                  ->whereMonth('created_at', substr($request->month, 5, 2));
        }
        
        if ($request->has('staff') && $request->staff) {
            $query->where('user_id', $request->staff);
        }
        
        if ($request->has('business_unit') && $request->business_unit) {
            $query->whereHas('user.staff', function($q) use ($request) {
                $q->where('business_unit', $request->business_unit);
            });
        }
        
        if ($request->has('application_type') && $request->application_type) {
            $query->where('application_type', $request->application_type);
        }

        if ($request->has('status') && $request->status) {
            $query->where('status', $request->status);
        }

        $applications = $query->orderBy('created_at', 'desc')->get();

        // Transform the data to ensure proper format for React
        $transformedApplications = $applications->map(function ($application) {
            // Safely extract applicant name
            $applicantName = 'N/A';
            if ($application->user && $application->user->staff) {
                $applicantName = $application->user->staff->full_name;
            } elseif ($application->user && $application->user->full_name) {
                $applicantName = $application->user->full_name;
            }

            // Safely extract business unit
            $businessUnit = 'N/A';
            if ($application->user && $application->user->staff) {
                $businessUnit = $application->user->staff->business_unit;
            } elseif ($application->user && $application->user->business_unit) {
                $businessUnit = $application->user->business_unit;
            }

            // Safely extract designation (ensure it's a string, not object)
            $designation = 'N/A';
            if ($application->user && 
                $application->user->staff && 
                $application->user->staff->designation) {
                
                // Handle designation object properly
                if (is_object($application->user->staff->designation)) {
                    $designation = $application->user->staff->designation->name ?? 'N/A';
                } else {
                    $designation = $application->user->staff->designation;
                }
            }

            return [
                'id' => $application->id,
                'application_id' => $application->application_id,
                'application_type' => $application->application_type,
                'user_id' => $application->user_id,
                'comment' => $application->comment,
                'status' => $application->status,
                'amount' => $application->amount,
                'cheque_number' => $application->cheque_number,
                'disbursement_status' => $application->disbursement_status,
                'receipt_confirmation' => $application->receipt_confirmation,
                'current_approval_level' => $application->current_approval_level,
                'approval_history' => $application->approval_history,
                'created_at' => $application->created_at,
                'updated_at' => $application->updated_at,
                
                // User data with safe object handling
                'user' => $application->user ? [
                    'id' => $application->user->id,
                    'staff_number' => $application->user->staff_number,
                    'email' => $application->user->email,
                    'role' => $application->user->role,
                    'is_active' => $application->user->is_active,
                    'full_name' => $applicantName, // Use the safely extracted name
                    'business_unit' => $businessUnit, // Use the safely extracted business unit
                    'designation' => $designation, // Ensure this is always a string
                    
                    // Staff data with safe object handling
                    'staff' => $application->user->staff ? [
                        'staff_number' => $application->user->staff->staff_number,
                        'full_name' => $application->user->staff->full_name,
                        'business_unit' => $application->user->staff->business_unit,
                        'designation' => $designation, // Use the safely extracted designation
                        'is_active' => $application->user->staff->is_active
                    ] : null
                ] : null,
                
                'documents' => $application->documents,
                
                // Direct fields for easy access in React
                'applicant_name' => $applicantName,
                'applicant_business_unit' => $businessUnit,
                'applicant_designation' => $designation
            ];
        });

        \Log::info('Analytics data fetched successfully', [
            'total_applications' => $applications->count(),
            'sample_data' => $transformedApplications->first() ? [
                'id' => $transformedApplications->first()['id'],
                'applicant_name' => $transformedApplications->first()['applicant_name'],
                'business_unit' => $transformedApplications->first()['applicant_business_unit'],
                'designation_type' => gettype($transformedApplications->first()['applicant_designation'])
            ] : 'No data'
        ]);

        return response()->json($transformedApplications);
        
    } catch (\Exception $e) {
        \Log::error('Error fetching analytics data:', [
            'error' => $e->getMessage(),
            'trace' => $e->getTraceAsString()
        ]);
        return response()->json(['error' => 'Failed to fetch analytics data'], 500);
    }
}
}