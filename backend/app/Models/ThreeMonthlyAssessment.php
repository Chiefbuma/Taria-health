<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ThreeMonthlyAssessment extends Model
{
    use HasFactory;

    protected $table = 'three_monthly_assessments';

     protected $fillable = [
        'onboarding_id',
        'user_id',
        'hba1c',
        'ldl',
        'bp',
        'height',
        'weight',
        'bmi',
        'serum_creatinine',
        'physical_activity_level',
        'nutrition',
        'exercise',
        'sleep_mental_health',
        'medication_adherence',
        'assessment_date',
        'revenue',
        
    ];

    protected $casts = [
        'hba1c' => 'float',
        'ldl' => 'float',
         'height'=> 'float',
        'weight' => 'float',
        'bmi' => 'float',
        'serum_creatinine' => 'float',
        'revenue' => 'float',
        'assessment_date' => 'date',
    ];

    public function onboarding()
    {
        return $this->belongsTo(Onboarding::class);
    }
}