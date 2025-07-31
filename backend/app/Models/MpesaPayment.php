<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MpesaPayment extends Model
{
    use HasFactory;

    protected $table = 'mpesa';

    protected $fillable = [
        'onboarding_id',
        'mpesa_reference',
        'client_name',
        'phone_number',
        'amount',
        'transaction_type',
        'status',
        'confirmation_code',
        'user_id', // Added if used in MpesaPaymentController
    ];

    protected $casts = [
        'amount' => 'decimal:2',
    ];

    public function onboarding()
    {
        return $this->belongsTo(Onboarding::class, 'onboarding_id');
    }
}