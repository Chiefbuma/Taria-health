<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Onboarding extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'onboardings';

    protected $fillable = [
        'user_id',
        'first_name',
        'middle_name',
        'last_name',
        'date_of_birth',
        'emr_number',
        'payer_id',
        'clinic_id',
        'insurance_id',
        'diagnoses',
        'medications',
        'age',
        'sex',
        'date_of_onboarding',
        'emergency_contact_name',
        'emergency_contact_phone',
        'emergency_contact_relation',
        'brief_medical_history',
        'date_of_diagnosis',
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
        'consent_to_telehealth',
        'consent_to_risks',
        'consent_to_data_use',
        'payment_method',
        'payment_id',
        'payment_status',
        'mpesa_number',
        'mpesa_reference',
        'insurance_provider',
    ];

    protected $casts = [
        'diagnoses' => 'array',
        'medications' => 'array',
        'is_active' => 'boolean',
        'has_weighing_scale' => 'boolean',
        'has_glucometer' => 'boolean',
        'has_bp_machine' => 'boolean',
        'has_tape_measure' => 'boolean',
        'consent_to_telehealth' => 'boolean',
        'consent_to_risks' => 'boolean',
        'consent_to_data_use' => 'boolean',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function payer()
    {
        return $this->belongsTo(Payer::class);
    }

    public function clinic()
    {
        return $this->belongsTo(Clinic::class);
    }

    public function insurance()
    {
        return $this->belongsTo(Insurance::class);
    }

    public function medicationUses()
    {
        return $this->hasMany(MedicationUse::class, 'onboarding_id');
    }

    public function mpesaPayment()
    {
        return $this->hasOne(MpesaPayment::class);
    }

    public function medications()
    {
        return $this->hasManyThrough(
            Medication::class,
            MedicationUse::class,
            'onboarding_id',
            'medication_id',
            'id',
            'medication_id'
        );
    }
}