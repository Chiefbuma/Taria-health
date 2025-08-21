<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('six_monthly_assessments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('onboarding_id')->constrained()->onDelete('cascade');
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->float('hba1c')->nullable();
            $table->float('ldl')->nullable();
            $table->string('bp')->nullable();
            $table->float('height')->nullable();
            $table->float('weight')->nullable();
             $table->string('ecg')->nullable();
            $table->float('bmi')->nullable();
            $table->float('serum_creatinine')->nullable();
            $table->string('physical_activity_level')->nullable();
            $table->string('nutrition')->nullable();
            $table->string('exercise')->nullable();
            $table->string('sleep_mental_health')->nullable();
            $table->string('medication_adherence')->nullable();
            $table->date('assessment_date');
            $table->float('revenue');
            
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('six_monthly_assessments');
    }
};