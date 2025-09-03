```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Create medications table
        Schema::create('medications', function (Blueprint $table) {
            $table->id('id');
            $table->string('item_name')->unique();
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
            $table->foreignId('medication_id')->constrained('medications', 'id')->onDelete('cascade')->unique();
            $table->foreignId('onboarding_id')->constrained('onboardings', 'id')->onDelete('cascade');
            $table->integer('days_supplied')->nullable();
            $table->enum('frequency', ['daily', 'twice_daily', 'weekly', 'as_needed'])->default('daily')->nullable();
            $table->integer('no_pills_dispensed')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('medication_uses');
        Schema::dropIfExists('medications');
    }
};