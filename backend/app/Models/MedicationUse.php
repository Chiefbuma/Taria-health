<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MedicationUse extends Model
{
    protected $fillable = ['medication_id', 'onboarding_id', 'days_supplied', 'no_pills_dispensed', 'frequency'];

    public function medication()
    {
        return $this->belongsTo(Medication::class, 'medication_id', 'id');
    }
}
