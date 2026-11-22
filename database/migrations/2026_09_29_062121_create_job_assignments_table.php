<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('job_assignments', function (Blueprint $table) {

            $table->id('assignment_id');

            /*
            |--------------------------------------------------------------------------
            | SERVICE REQUEST
            |--------------------------------------------------------------------------
            */

            $table->foreignId('request_id')
                ->unique()
                ->constrained('repair_requests', 'request_id')
                ->cascadeOnDelete();


            /*
            |--------------------------------------------------------------------------
            | TECHNICIAN
            |--------------------------------------------------------------------------
            */

            $table->foreignId('technician_id')
                ->constrained(
                    'technician_profiles',
                    'technician_id'
                )
                ->cascadeOnDelete();


            /*
            |--------------------------------------------------------------------------
            | ADMIN WHO ASSIGNED THE JOB
            |--------------------------------------------------------------------------
            */

            $table->foreignId('assigned_by')
                ->nullable()
                ->constrained(
                    'users',
                    'user_id'
                )
                ->nullOnDelete();


            /*
            |--------------------------------------------------------------------------
            | ASSIGNMENT INFORMATION
            |--------------------------------------------------------------------------
            */

            $table->timestamp('assigned_at')
                ->nullable();

            $table->enum('status', [
                'assigned',
                'accepted',
                'rejected',
                'on_the_way',
                'in_progress',
                'completed',
                'cancelled',
            ])->default('assigned');

            $table->text('remarks')
                ->nullable();


            /*
            |--------------------------------------------------------------------------
            | TIMESTAMPS
            |--------------------------------------------------------------------------
            */

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('job_assignments');
    }
};