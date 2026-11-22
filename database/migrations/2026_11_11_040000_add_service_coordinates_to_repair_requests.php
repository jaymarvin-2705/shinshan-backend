<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('repair_requests', function (Blueprint $table) {
            $table->decimal('service_latitude', 10, 8)->nullable();
            $table->decimal('service_longitude', 11, 8)->nullable();
        });

        Schema::table('technician_profiles', function (Blueprint $table) {
            $table->timestamp('current_location_updated_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('repair_requests', function (Blueprint $table) {
            $table->dropColumn(['service_latitude', 'service_longitude']);
        });

        Schema::table('technician_profiles', function (Blueprint $table) {
            $table->dropColumn('current_location_updated_at');
        });
    }
};
