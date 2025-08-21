<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MedicationUse extends Model
{
    use HasFactory;

    protected $table = 'medication_uses';

    protected $fillable = [
        'medication_id',
        'onboarding_id',
        'days_supplied',
        'no_pills_dispensed',
        'frequency',
    ];

    protected $casts = [
        'days_supplied' => 'integer',
        'no_pills_dispensed' => 'integer',
    ];

    /**
     * Get the medication associated with this use.
     */
    public function medication()
    {
        return $this->belongsTo(Medication::class, 'medication_id');
    }

    /**
     * Get the onboarding associated with this use.
     */
    public function onboarding()
    {
        return $this->belongsTo(Onboarding::class, 'onboarding_id');
    }
}