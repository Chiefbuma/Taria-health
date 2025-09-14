<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        // Create insurance table (without onboarding_id initially)
        Schema::create('insurance', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->foreignId('payer_id')->constrained('payers')->onDelete('cascade');
            $table->string('policy_number');
            $table->decimal('claim_amount', 10, 2)->nullable();
            $table->string('is_approved')->default('pending');
            $table->string('approval_document_path')->nullable();
            $table->string('approval_document_name')->nullable();
            $table->timestamps();
            $table->index('policy_number');
            $table->index('payer_id');
            $table->index('user_id'); // Added index for frequent lookups
            $table->index('is_approved'); // Added index for filtering by approval status
        });

        // Create onboardings table
        Schema::create('onboardings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->onDelete('cascade');
            $table->string('first_name');
            $table->string('middle_name')->nullable();
            $table->string('last_name');
            $table->date('date_of_birth');
            $table->string('emr_number')->nullable();
            $table->foreignId('payer_id')->nullable()->constrained('payers')->onDelete('set null');
            $table->foreignId('clinic_id')->nullable()->constrained('clinics', 'id')->onDelete('set null');
            $table->json('diagnoses')->nullable();
            $table->date('date_of_diagnosis')->nullable();
            $table->json('medications')->nullable();
            $table->integer('age');
            $table->enum('sex', ['male', 'female', 'other']);
            $table->date('date_of_onboarding')->nullable();
            $table->string('emergency_contact_name');
            $table->string('emergency_contact_phone', 20);
            $table->string('emergency_contact_relation');
            $table->text('brief_medical_history')->nullable();
            $table->integer('years_since_diagnosis')->nullable();
            $table->text('past_medical_interventions')->nullable();
            $table->text('relevant_family_history')->nullable();
            $table->decimal('hba1c_baseline', 5, 2)->nullable();
            $table->decimal('ldl_baseline', 5, 2)->nullable();
            $table->string('bp_baseline')->nullable();
            $table->decimal('weight_baseline', 5, 2)->nullable();
            $table->decimal('height', 5, 2)->nullable();
            $table->decimal('bmi_baseline', 5, 2)->nullable();
            $table->decimal('serum_creatinine_baseline', 5, 2)->nullable();
            $table->string('ecg_baseline')->nullable();
            $table->string('physical_activity_level')->nullable();
            $table->decimal('weight_loss_target', 5, 2)->nullable();
            $table->decimal('hba1c_target', 5, 2)->nullable();
            $table->string('bp_target')->nullable();
            $table->string('activity_goal')->nullable();
            $table->boolean('has_weighing_scale')->default(false);
            $table->boolean('has_glucometer')->default(false);
            $table->boolean('has_bp_machine')->default(false);
            $table->boolean('has_tape_measure')->default(false);
            $table->text('dietary_restrictions')->nullable();
            $table->text('allergies_intolerances')->nullable();
            $table->text('lifestyle_factors')->nullable();
            $table->text('physical_limitations')->nullable();
            $table->text('psychosocial_factors')->nullable();
            $table->date('initial_consultation_date')->nullable();
            $table->date('follow_up_review1')->nullable();
            $table->date('follow_up_review2')->nullable();
            $table->date('additional_review')->nullable();
            $table->date('consent_date');
            $table->string('activation_code')->nullable();
            $table->boolean('is_active')->default(false);
            $table->boolean('consent_to_telehealth')->default(false);
            $table->boolean('consent_to_risks')->default(false);
            $table->boolean('consent_to_data_use')->default(false);
            $table->string('payment_method')->nullable();
            $table->unsignedBigInteger('payment_id')->nullable();
            $table->string('payment_status')->nullable()->default('pending');
            $table->string('mpesa_number', 20)->nullable();
            $table->string('mpesa_reference')->nullable();
            $table->foreignId('insurance_id')->nullable()->constrained('insurance')->onDelete('set null');
            $table->timestamps();
            $table->softDeletes();
            $table->index('first_name'); // Added index for searching by first name
            $table->index('last_name'); // Added index for searching by last name
            $table->index('date_of_birth'); // Added index for filtering by DOB
            $table->index('emergency_contact_phone'); // Added index for searching by emergency contact
            $table->index('payment_status'); // Added index for filtering by payment status
            $table->index('clinic_id'); // Added index for clinic-based queries
            $table->index('payer_id'); // Added index for payer-based queries
            $table->index('is_active'); // Added index for filtering active/inactive records
        });

        // Create mpesa table (without onboarding_id initially)
        Schema::create('mpesa', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->string('mpesa_reference')->nullable();
            $table->string('client_name')->nullable();
            $table->string('phone_number', 20)->nullable();
            $table->decimal('amount', 10, 2)->nullable();
            $table->string('transaction_type')->nullable();
            $table->string('status')->default('pending');
            $table->string('confirmation_code')->nullable();
            $table->timestamps();
            $table->index('mpesa_reference');
            $table->index('phone_number');
            $table->index('user_id'); // Added index for user-based queries
            $table->index('status'); // Added index for filtering by transaction status
        });

        // Add onboarding_id column and foreign key to insurance table
        Schema::table('insurance', function (Blueprint $table) {
            $table->foreignId('onboarding_id')->nullable()->after('id')->constrained('onboardings')->onDelete('set null');
            $table->index('onboarding_id'); // Added index for onboarding_id lookups
        });

        // Add onboarding_id column and foreign key to mpesa table
        Schema::table('mpesa', function (Blueprint $table) {
            $table->foreignId('onboarding_id')->nullable()->after('id')->constrained('onboardings')->onDelete('set null');
            $table->index('onboarding_id'); // Added index for onboarding_id lookups
        });
    }

    public function down()
    {
        // Drop foreign keys and columns
        Schema::table('mpesa', function (Blueprint $table) {
            $table->dropForeign(['onboarding_id']);
            $table->dropIndex(['onboarding_id']);
            $table->dropColumn('onboarding_id');
        });

        Schema::table('insurance', function (Blueprint $table) {
            $table->dropForeign(['onboarding_id']);
            $table->dropIndex(['onboarding_id']);
            $table->dropColumn('onboarding_id');
        });

        // Drop tables in reverse order
        Schema::dropIfExists('mpesa');
        Schema::dropIfExists('onboardings');
        Schema::dropIfExists('insurance');
    }
};