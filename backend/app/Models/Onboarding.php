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
        'middle_name',
        'last_name',
        'date_of_birth',
        'age',
        'sex',
        'clinic_id',
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
        'payment_method',
        'payment_id',
        'payment_status',
        'mpesa_number',
        'mpesa_reference',
        'insurance_provider',
        'insurance_id',
        'consent_to_telehealth',
        'consent_to_risks',
        'consent_to_data_use',
    ];

    protected $casts = [
        'date_of_birth' => 'date',
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
        'consent_to_telehealth' => 'boolean',
        'consent_to_risks' => 'boolean',
        'consent_to_data_use' => 'boolean',
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
        return $this->middle_name
            ? "{$this->first_name} {$this->middle_name} {$this->last_name}"
            : "{$this->first_name} {$this->last_name}";
    }
}