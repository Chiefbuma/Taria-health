<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Medication extends Model
{
    use SoftDeletes;

    protected $table = 'medications';
    protected $primaryKey = 'id';
    protected $fillable = ['item_name', 'description', 'dosage', 'frequency', 'is_active'];

    public function medicationUses()
    {
        return $this->hasMany(MedicationUse::class, 'medication_id', 'id');
    }
}