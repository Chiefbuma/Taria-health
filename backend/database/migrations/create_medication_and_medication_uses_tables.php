<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Create medication table
        Schema::create('medication', function (Blueprint $table) {
            $table->id('medication_id');
            $table->string('item_name');
            $table->text('description')->nullable();
            $table->string('dosage')->nullable();
            $table->string('frequency')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();
        });

        // Create medication_uses table
        Schema::create('medication_uses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('medication_id')->constrained('medication', 'medication_id')->onDelete('cascade');
            $table->foreignId('onboarding_id')->constrained('onboardings', 'id')->onDelete('cascade');
            $table->integer('days_supplied');
            $table->integer('no_pills_dispensed');
            $table->enum('frequency', ['daily', 'weekly', 'monthly'])->default('daily');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('medication_uses');
        Schema::dropIfExists('medication');
    }
};