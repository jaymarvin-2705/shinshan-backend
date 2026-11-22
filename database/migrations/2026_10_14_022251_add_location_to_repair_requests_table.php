<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('repair_requests', function (Blueprint $table) {
            // Nullable so the 3 existing requests stay valid.
            $table->string('barangay', 100)->nullable()->after('description');
            $table->string('purok', 50)->nullable()->after('barangay');
            $table->string('landmark', 120)->nullable()->after('purok');
        });
    }

    public function down(): void
    {
        Schema::table('repair_requests', function (Blueprint $table) {
            $table->dropColumn(['barangay', 'purok', 'landmark']);
        });
    }
};