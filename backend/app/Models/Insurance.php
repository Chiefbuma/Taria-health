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
        'is_approved',
        'user_id',
        'claim_amount',
        'approval_document_path',
        'approval_document_name',
    ];

   

    public function onboarding()
    {
        return $this->belongsTo(Onboarding::class, 'onboarding_id');
    }
}