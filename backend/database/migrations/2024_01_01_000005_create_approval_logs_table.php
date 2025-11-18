<?php
// database/migrations/2024_01_01_000005_create_approval_logs_table.php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateApprovalLogsTable extends Migration
{
    public function up()
    {
        Schema::create('approval_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('application_id')->constrained('applications')->onDelete('cascade');
            $table->foreignId('approver_id')->constrained('users');
            $table->enum('approval_level', ['chair', 'treasurer', 'disbursement']);
            $table->enum('action', ['approved', 'rejected', 'requested_changes']);
            $table->text('comments')->nullable();
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('approval_logs');
    }
}