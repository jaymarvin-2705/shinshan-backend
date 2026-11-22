<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('repair_requests', function (Blueprint $table) {
            $table->id('request_id');

            $table->foreignId('client_id')
                ->constrained('client_profiles', 'client_id')
                ->cascadeOnDelete();

            $table->foreignId('category_id')
                ->constrained('issue_categories', 'category_id')
                ->restrictOnDelete();

            $table->string('title', 150);
            $table->text('description');

            $table->string('photo', 255)->nullable();

            $table->enum('priority', [
                'low',
                'medium',
                'high'
            ])->default('medium');

            $table->enum('status', [
                'pending',
                'assigned',
                'on_the_way',
                'in_progress',
                'completed',
                'cancelled'
            ])->default('pending');

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('repair_requests');
    }
};