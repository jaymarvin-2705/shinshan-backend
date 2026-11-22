<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
       Schema::create('repair_updates', function (Blueprint $table) {
    $table->id('update_id');

    $table->foreignId('assignment_id')
        ->constrained('job_assignments', 'assignment_id')
        ->cascadeOnDelete();

    $table->enum('status', [
        'accepted',
        'on_the_way',
        'arrived',
        'repair_started',
        'completed',
        'cancelled'
    ]);

    $table->text('remarks')->nullable();
    $table->string('image', 255)->nullable();

    $table->timestamps();
});
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('repair_updates');
    }
};
