<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Onboarding extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'user_id',
        'first_name',
        'last_name',
        'patient_no',
        'emr_number',
        'payer_id',
        'clinic_id',
        'diagnoses',
        'medications',
        'age',
        'sex',
        'date_of_onboarding',
        'emergency_contact_name',
        'emergency_contact_phone',
        'emergency_contact_relation',
        'brief_medical_history',
        'years_since_diagnosis',
        'past_medical_interventions',
        'relevant_family_history',
        'hba1c_baseline',
        'ldl_baseline',
        'bp_baseline',
        'weight_baseline',
        'height',
        'bmi_baseline',
        'serum_creatinine_baseline',
        'ecg_baseline',
        'physical_activity_level',
        'weight_loss_target',
        'hba1c_target',
        'bp_target',
        'activity_goal',
        'has_weighing_scale',
        'has_glucometer',
        'has_bp_machine',
        'has_tape_measure',
        'dietary_restrictions',
        'allergies_intolerances',
        'lifestyle_factors',
        'physical_limitations',
        'psychosocial_factors',
        'initial_consultation_date',
        'follow_up_review1',
        'follow_up_review2',
        'additional_review',
        'consent_date',
        'activation_code',
        'is_active',
        'payment_method', // Added
        'payment_id',     // Added
        'payment_status', // Added
        'mpesa_number',   // Added
        'mpesa_reference', // Added
        'insurance_provider', // Added
        'insurance_id',   // Added
    ];

    protected $casts = [
        'date_of_onboarding' => 'date',
        'diagnoses' => 'array',
        'medications' => 'array',
        'initial_consultation_date' => 'date',
        'follow_up_review1' => 'date',
        'follow_up_review2' => 'date',
        'additional_review' => 'date',
        'consent_date' => 'date',
        'has_weighing_scale' => 'boolean',
        'has_glucometer' => 'boolean',
        'has_bp_machine' => 'boolean',
        'has_tape_measure' => 'boolean',
        'is_active' => 'boolean',
        'hba1c_baseline' => 'decimal:2',
        'ldl_baseline' => 'decimal:2',
        'weight_baseline' => 'decimal:2',
        'height' => 'decimal:2',
        'bmi_baseline' => 'decimal:2',
        'serum_creatinine_baseline' => 'decimal:2',
        'weight_loss_target' => 'decimal:2',
        'hba1c_target' => 'decimal:2',
        'deleted_at' => 'datetime',
        'consent_to_telehealth' => 'boolean', // Added
        'consent_to_risks' => 'boolean',      // Added
        'consent_to_data_use' => 'boolean',   // Added
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function clinic()
    {
        return $this->belongsTo(Clinic::class);
    }

    public function mpesaPayment()
    {
        return $this->hasOne(MpesaPayment::class, 'id', 'payment_id')
                    ->where('payment_method', 'mpesa');
    }

    public function insurance()
    {
        return $this->hasOne(Insurance::class, 'id', 'payment_id')
                    ->where('payment_method', 'insurance');
    }

    public function getFullNameAttribute()
    {
        return "{$this->first_name} {$this->last_name}";
    }
}