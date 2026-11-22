<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('feedback', function (Blueprint $table) {
            $table->id('feedback_id');

            $table->foreignId('request_id')
                ->unique()
                ->constrained('repair_requests', 'request_id')
                ->cascadeOnDelete();

            $table->foreignId('client_id')
                ->constrained('client_profiles', 'client_id')
                ->cascadeOnDelete();

            $table->foreignId('technician_id')
                ->constrained('technician_profiles', 'technician_id')
                ->restrictOnDelete();

            $table->unsignedTinyInteger('rating');

            $table->text('comment')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('feedback');
    }
};