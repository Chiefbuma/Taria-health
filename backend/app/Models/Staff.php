<?php
// app/Models/Staff.php
namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Staff extends Model
{
    use HasFactory;

    protected $fillable = [
        'staff_number',
        'full_name',
        'date_of_joining',
        'designation_id',
        'personal_email',
        'business_unit',
        'mobile',
        'is_active',
    ];

    protected $casts = [
        'date_of_joining' => 'date',
        'is_active' => 'boolean',
    ];

    /**
     * Get the designation that owns the staff.
     */
    public function designation()
    {
        return $this->belongsTo(Designation::class);
    }

    /**
     * Get the user account associated with the staff.
     */
    public function user()
    {
        return $this->hasOne(User::class, 'staff_number', 'staff_number');
    }

    /**
     * Get all applications for the staff through user relationship.
     */
    public function applications()
    {
        return $this->hasManyThrough(
            Application::class,
            User::class,
            'staff_number',
            'user_id',
            'staff_number',
            'id'
        );
    }

    /**
     * Scope a query to only include active staff.
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope a query to only include inactive staff.
     */
    public function scopeInactive($query)
    {
        return $query->where('is_active', false);
    }

    /**
     * Scope a query to search staff by name or staff number.
     */
    public function scopeSearch($query, $search)
    {
        return $query->where(function ($q) use ($search) {
            $q->where('full_name', 'like', "%{$search}%")
              ->orWhere('staff_number', 'like', "%{$search}%")
              ->orWhere('personal_email', 'like', "%{$search}%");
        });
    }

    /**
     * Scope a query to filter staff by designation.
     */
    public function scopeByDesignation($query, $designationId)
    {
        return $query->where('designation_id', $designationId);
    }

    /**
     * Scope a query to filter staff by business unit.
     */
    public function scopeByBusinessUnit($query, $businessUnit)
    {
        return $query->where('business_unit', $businessUnit);
    }

    /**
     * Check if staff has a user account.
     */
    public function hasUserAccount()
    {
        return !is_null($this->user);
    }

    /**
     * Get the staff member's designation name.
     */
    public function getDesignationNameAttribute()
    {
        return $this->designation ? $this->designation->name : 'N/A';
    }

    /**
     * Get the staff member's display name with staff number.
     */
    public function getDisplayNameAttribute()
    {
        return "{$this->full_name} ({$this->staff_number})";
    }

    /**
     * Get the staff member's tenure in years.
     */
    public function getTenureInYearsAttribute()
    {
        if (!$this->date_of_joining) {
            return null;
        }

        $joiningDate = $this->date_of_joining;
        $now = now();
        
        return $joiningDate->diffInYears($now);
    }

    /**
     * Get the staff member's tenure formatted.
     */
    
    public function getFormattedTenureAttribute()
    {
        $tenure = $this->tenure_in_years;
        
        if (is_null($tenure)) {
            return 'N/A';
        }

        if ($tenure == 0) {
            return 'Less than a year';
        }

        return $tenure . ' ' . ($tenure == 1 ? 'year' : 'years');
    }

    /**
     * Activate the staff member.
     */
    public function activate()
    {
        $this->update(['is_active' => true]);
        return $this;
    }

    /**
     * Deactivate the staff member.
     */
    public function deactivate()
    {
        $this->update(['is_active' => false]);
        return $this;
    }

    /**
     * Check if staff member can be deleted (no user account).
     */
    public function canBeDeleted()
    {
        return !$this->hasUserAccount();
    }

    /**
     * Get staff members who don't have user accounts.
     */
    public static function withoutUserAccount()
    {
        return static::whereDoesntHave('user')->get();
    }

    /**
     * Get staff members with user accounts.
     */
    public static function withUserAccount()
    {
        return static::whereHas('user')->get();
    }

    /**
     * Boot method for model events.
     */
    protected static function boot()
    {
        parent::boot();

        // Prevent deletion if staff has user account
        static::deleting(function ($staff) {
            if ($staff->hasUserAccount()) {
                throw new \Exception('Cannot delete staff member with associated user account.');
            }
        });
    }
}