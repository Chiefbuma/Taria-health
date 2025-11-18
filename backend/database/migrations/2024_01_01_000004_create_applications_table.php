<?php
// database/migrations/2024_01_01_000003_create_applications_table.php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateApplicationsTable extends Migration
{
    public function up()
    {
        Schema::create('applications', function (Blueprint $table) {
            $table->id();
            $table->string('application_id')->unique();
            $table->enum('application_type', ['childbirth', 'wedding', 'bereavement', 'logistics']);
            $table->foreignId('user_id')->constrained('users');
            $table->text('comment')->nullable();
            $table->enum('status', ['pending', 'approved', 'rejected', 'needs_information'])->default('pending');
            
            // Financial fields
            $table->integer('amount')->nullable();
            $table->string('cheque_number')->nullable();
            $table->enum('disbursement_status', ['pending', 'processed', 'completed'])->default('pending');
            $table->enum('receipt_confirmation', ['pending', 'received'])->default('pending'); // Changed to enum
            
            // Approval tracking
            $table->enum('current_approval_level', ['chair', 'treasurer', 'disbursement'])->default('chair');
            $table->json('approval_history')->nullable();
            
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('applications');
    }
}