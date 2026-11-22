<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('schedules', function (Blueprint $table) {
            $table->id('schedule_id');

            $table->foreignId('assignment_id')
                ->unique()
                ->constrained('job_assignments', 'assignment_id')
                ->cascadeOnDelete();

            $table->date('schedule_date');
            $table->time('schedule_time');

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('schedules');
    }
};