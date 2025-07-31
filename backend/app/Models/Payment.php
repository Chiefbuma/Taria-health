<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Payment extends Model
{
    use HasFactory;

    protected $fillable = [
        'onboarding_id',
        'mpesa_number',
        'mpesa_code',
        'mpesa_reference',
        'insurance_provider',
        'policy_number',
        'method',
        'reference',
        'amount',
        'status'
    ];

    public function onboarding()
    {
        return $this->belongsTo(Onboarding::class, 'onboarding_id');
    }
}