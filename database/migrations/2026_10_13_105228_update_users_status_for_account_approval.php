
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->enum('status', [
                'active',
                'inactive',
                'pending',
                'rejected',
            ])->default('active')->change();
        });
    }

    public function down(): void
    {
        // Restore the original status values.
        // Convert statuses unsupported by the original enum first.
        Schema::table('users', function (Blueprint $table) {
            $table->string('status', 20)->default('active')->change();
        });

        DB::table('users')
            ->whereNotIn('status', ['active', 'inactive'])
            ->update(['status' => 'inactive']);

        Schema::table('users', function (Blueprint $table) {
            $table->enum('status', [
                'active',
                'inactive',
            ])->default('active')->change();
        });
    }
};
