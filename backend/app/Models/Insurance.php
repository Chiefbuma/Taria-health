<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Insurance extends Model
{
    use HasFactory;

    protected $table = 'insurance';

    protected $fillable = [
        'onboarding_id',
        'insurance_provider',
        'policy_number',
        'insurance_type',
        'is_approved',
        'user_id', // Added if used in InsuranceController
    ];

    protected $casts = [
        'is_approved' => 'boolean',
    ];

    public function onboarding()
    {
        return $this->belongsTo(Onboarding::class, 'onboarding_id');
    }
}