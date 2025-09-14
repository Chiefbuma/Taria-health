<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('email')->unique(); // Unique constraint includes index
            $table->string('phone')->nullable();
            $table->string('role')->default('user'); // Added role column
            $table->boolean('is_active')->default(true); // Added is_active column
            $table->timestamp('email_verified_at')->nullable();
            $table->foreignId('payer_id')->nullable()->constrained('payers')->onDelete('set null');
            $table->string('password');
            $table->rememberToken();
            $table->timestamps();
            $table->index('phone'); // Added index for searching by phone
            $table->index('role'); // Added index for filtering by role
            $table->index('is_active'); // Added index for filtering by active status
            $table->index('payer_id'); // Added index for payer-based queries
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('users');
    }
};