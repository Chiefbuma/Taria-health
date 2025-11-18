<?php
// database/migrations/xxxx_xx_xx_xxxxxx_create_staff_table.php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('staff', function (Blueprint $table) {
            $table->id();
            $table->string('staff_number')->unique();
            $table->string('full_name');
            $table->date('date_of_joining')->nullable();

            // Change from string to foreign key
            $table->foreignId('designation_id')->nullable()->constrained('designations')->onDelete('set null');

            $table->string('personal_email')->nullable();
            $table->string('business_unit')->nullable();
            $table->string('mobile')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('staff');
    }
};