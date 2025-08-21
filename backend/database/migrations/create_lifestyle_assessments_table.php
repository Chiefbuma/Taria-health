<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('lifestyle_assessments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('onboarding_id')->constrained('onboardings')->onDelete('cascade');
            $table->text('nutrition')->nullable();
            $table->text('exercise')->nullable();
            $table->text('sleep_mental_health')->nullable();
            $table->text('medication_adherence')->nullable();
            $table->date('assessment_date');
            $table->date('next_assessment_date');
            $table->decimal('revenue', 10, 2);
            $table->string('physician');
            $table->string('navigator');
            $table->timestamps();

            $table->index('onboarding_id');
        });
    }

    public function down()
    {
        Schema::dropIfExists('lifestyle_assessments');
    }
};