<?php
// app/Models/Application.php
namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Application extends Model
{
    use HasFactory;

    protected $fillable = [
        'application_id',
        'application_type',
        'user_id',
        'comment',
        'status',
        'amount',
        'cheque_number',
        'disbursement_status',
        'receipt_confirmation', // Now string enum
        'current_approval_level',
        'approval_history'
    ];

    protected $casts = [
        'approval_history' => 'array',
        'amount' => 'integer'
        // Removed receipt_confirmation boolean casting
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function documents()
    {
        return $this->hasMany(Document::class);
    }

    public function approvalLogs()
    {
        return $this->hasMany(ApprovalLog::class);
    }

    public function getRequiredDocumentsAttribute()
    {
        $requirements = [
            'wedding' => ['wedding_invitation', 'marriage_certificate'],
            'childbirth' => ['birth_certificate'],
            'bereavement' => ['death_certificate', 'burial_permit', 'relationship_proof'],
            'logistics' => ['receipts']
        ];

        return $requirements[$this->application_type] ?? [];
    }

    public function canMoveToNextLevel()
    {
        $currentLevel = $this->current_approval_level;
        $levels = ['chair', 'treasurer', 'disbursement'];
        $currentIndex = array_search($currentLevel, $levels);
        
        return $currentIndex !== false && $currentIndex < count($levels) - 1;
    }

    public function getNextApprovalLevel()
    {
        $levels = ['chair', 'treasurer', 'disbursement'];
        $currentIndex = array_search($this->current_approval_level, $levels);
        
        return $levels[$currentIndex + 1] ?? null;
    }

    // Accessor for applicant details through user->staff relationship
    public function getApplicantNameAttribute()
    {
        return $this->user->staff->full_name ?? $this->user->full_name ?? 'N/A';
    }

    public function getApplicantDesignationAttribute()
    {
        return $this->user->staff->designation ?? $this->user->designation ?? 'N/A';
    }

    public function getApplicantBusinessUnitAttribute()
    {
        return $this->user->staff->business_unit ?? $this->user->business_unit ?? 'N/A';
    }

    // Accessor to format amount for display
    public function getFormattedAmountAttribute()
    {
        if (!$this->amount) return 'N/A';
        return number_format($this->amount, 0);
    }

    // Helper method to check if receipt is confirmed
    public function isReceiptConfirmed()
    {
        return $this->receipt_confirmation === 'received';
    }
}