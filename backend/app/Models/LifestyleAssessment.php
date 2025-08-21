<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class LifestyleAssessment extends Model
{
    use HasFactory;

    protected $table = 'lifestyle_assessments';

    protected $fillable = [
        'onboarding_id',
        'nutrition',
        'exercise',
        'sleep_mental_health',
        'medication_adherence',
        'assessment_date',
        'next_assessment_date',
        'revenue',
        'physician',
        'navigator',
    ];

    protected $casts = [
        'revenue' => 'decimal:2',
        'assessment_date' => 'date',
        'next_assessment_date' => 'date',
    ];

    public function onboarding()
    {
        return $this->belongsTo(Onboarding::class, 'onboarding_id');
    }
}