<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Mpesa extends Model
{
    use HasFactory;

    protected $table = 'mpesa';

    protected $fillable = [
        'user_id',
        'onboarding_id',
        'mpesa_reference',
        'client_name',
        'phone_number',
        'amount',
        'transaction_type',
        'status',
        'confirmation_code',
    ];

    public function onboarding()
    {
        return $this->belongsTo(Onboarding::class, 'onboarding_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
