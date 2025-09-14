<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up()
    {
        Schema::create('clinics', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('code')->unique(); // For clinic initials, already indexed due to unique constraint
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();
            $table->index('name'); // Added index for searching by clinic name
            $table->index('is_active'); // Added index for filtering by active status
        });
    }

    public function down()
    {
        Schema::dropIfExists('clinics');
    }
};