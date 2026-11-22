<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('repair_requests', function (Blueprint $table) {
            $table->string('priority', 20)->default('medium')->change();
        });
    }

    public function down(): void
    {
        DB::table('repair_requests')
            ->where('priority', 'very_high')
            ->update(['priority' => 'high']);

        Schema::table('repair_requests', function (Blueprint $table) {
            $table->enum('priority', ['low', 'medium', 'high'])
                ->default('medium')
                ->change();
        });
    }
};
