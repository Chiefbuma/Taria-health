<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Insurance extends Model
{
    use HasFactory;

    protected $table = 'insurance';

    protected $fillable = [
        'user_id',
        'onboarding_id',
        'payer_id',
        'policy_number',
        'claim_amount',
        'is_approved',
        'approval_document_path',
        'approval_document_name',
    ];

    public function onboarding()
    {
        return $this->hasOne(Onboarding::class, 'insurance_id', 'id');
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function payer()
    {
        return $this->belongsTo(Payer::class);
    }
}