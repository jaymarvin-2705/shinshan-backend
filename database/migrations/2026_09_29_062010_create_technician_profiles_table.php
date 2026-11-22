<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('technician_profiles', function (Blueprint $table) {
            $table->id('technician_id');

            $table->foreignId('user_id')
                ->unique()
                ->constrained('users', 'user_id')
                ->cascadeOnDelete();

            $table->string('service_area', 100)->nullable();

            $table->enum('availability', [
                'available',
                'busy',
                'offline'
            ])->default('available');

            $table->decimal('current_latitude', 10, 8)->nullable();
            $table->decimal('current_longitude', 11, 8)->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('technician_profiles');
    }
};