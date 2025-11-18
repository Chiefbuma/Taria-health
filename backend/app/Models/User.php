<?php
// app/Models/User.php
namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    protected $fillable = [
        'email',
        'staff_number',
        'role',
        'is_active',
        'password'
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected $casts = [
        'email_verified_at' => 'datetime',
        'is_active' => 'boolean',
    ];

    /**
     * Get the staff record associated with the user.
     */
    public function staff()
    {
        return $this->belongsTo(Staff::class, 'staff_number', 'staff_number');
    }

    public function applications()
    {
        return $this->hasMany(Application::class);
    }

    public function documents()
    {
        return $this->hasMany(Document::class);
    }

    public function approvalLogs()
    {
        return $this->hasMany(ApprovalLog::class, 'approver_id');
    }

    public function isApprover()
    {
        return in_array($this->role, ['chair', 'treasurer', 'disbursement']);
    }

    /**
     * Get the user's full name through staff relationship.
     */
    public function getFullNameAttribute()
    {
        return $this->staff ? $this->staff->full_name : 'N/A';
    }

    /**
     * Get the user's designation name through staff->designation relationship.
     */
    public function getDesignationAttribute()
    {
        if ($this->staff && $this->staff->designation) {
            return $this->staff->designation->name ?? 'N/A';
        }
        return 'N/A';
    }

    /**
     * Get the user's designation ID through staff relationship.
     */
    public function getDesignationIdAttribute()
    {
        return $this->staff ? $this->staff->designation_id : null;
    }

    /**
     * Get the user's business unit through staff relationship.
     */
    public function getBusinessUnitAttribute()
    {
        return $this->staff ? $this->staff->business_unit : 'N/A';
    }

    /**
     * Get the user's personal email through staff relationship.
     */
    public function getPersonalEmailAttribute()
    {
        return $this->staff ? $this->staff->personal_email : 'N/A';
    }

    /**
     * Get the user's mobile number through staff relationship.
     */
    public function getMobileAttribute()
    {
        return $this->staff ? $this->staff->mobile : 'N/A';
    }

    /**
     * Get the user's date of joining through staff relationship.
     */
    public function getDateOfJoiningAttribute()
    {
        return $this->staff ? $this->staff->date_of_joining : null;
    }

    /**
     * Scope a query to only include active users.
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope a query to only include users with specific roles.
     */
    public function scopeByRole($query, $role)
    {
        return $query->where('role', $role);
    }

    /**
     * Check if user has a specific role.
     */
    public function hasRole($role)
    {
        return $this->role === $role;
    }

    /**
     * Check if user has any of the given roles.
     */
    public function hasAnyRole(array $roles)
    {
        return in_array($this->role, $roles);
    }

    /**
     * Get the user's display name (for use in UI).
     */
    public function getDisplayNameAttribute()
    {
        return $this->full_name . ' (' . $this->staff_number . ')';
    }

    /**
     * Get the user's role in a formatted way.
     */
    public function getFormattedRoleAttribute()
    {
        return ucfirst($this->role);
    }
}