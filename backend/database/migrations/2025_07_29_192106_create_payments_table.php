<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('onboarding_id');
            $table->string('payment_method')->default('mpesa'); // mpesa, insurance, card
            $table->string('mpesa_reference')->nullable();
            $table->string('phone_number')->nullable();
            $table->string('transaction_id')->nullable();
            $table->decimal('amount', 10, 2);
            $table->string('currency')->default('KES');
            $table->string('insurance_provider')->nullable();
            $table->string('policy_number')->nullable();
            $table->string('status')->default('pending'); // pending, completed, failed
            $table->string('receipt_url')->nullable();
            $table->text('metadata')->nullable();
            $table->timestamps();

            $table->foreign('onboarding_id')
                  ->references('id')
                  ->on('onboardings')
                  ->onDelete('cascade');
        });
    }

    public function down()
    {
        Schema::dropIfExists('payments');
    }
};