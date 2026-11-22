<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (! Schema::hasColumn('users', 'firstname')) {
                $table->string('firstname', 80)->nullable();
            }

            if (! Schema::hasColumn('users', 'middlename')) {
                $table->string('middlename', 80)->nullable();
            }

            if (! Schema::hasColumn('users', 'lastname')) {
                $table->string('lastname', 80)->nullable();
            }

            $table->string('fullname', 255)->change();
            $table->string('email', 255)->change();
        });

        Schema::table('client_profiles', function (Blueprint $table) {
            if (! Schema::hasColumn('client_profiles', 'birth_date')) {
                $table->date('birth_date')->nullable();
            }

            if (! Schema::hasColumn('client_profiles', 'barangay')) {
                $table->string('barangay', 100)->nullable();
            }

            if (! Schema::hasColumn('client_profiles', 'purok')) {
                $table->string('purok', 50)->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $columns = [];

            foreach (['firstname', 'middlename', 'lastname'] as $column) {
                if (Schema::hasColumn('users', $column)) {
                    $columns[] = $column;
                }
            }

            if ($columns !== []) {
                $table->dropColumn($columns);
            }
        });

        Schema::table('client_profiles', function (Blueprint $table) {
            $columns = [];

            foreach (['birth_date', 'barangay', 'purok'] as $column) {
                if (Schema::hasColumn('client_profiles', $column)) {
                    $columns[] = $column;
                }
            }

            if ($columns !== []) {
                $table->dropColumn($columns);
            }
        });
    }
};
