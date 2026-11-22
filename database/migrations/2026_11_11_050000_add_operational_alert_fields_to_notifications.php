<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('notifications', function (Blueprint $table) {
            $table->string('notification_type', 40)->nullable()->after('announcement_id');
            $table->string('priority', 20)->nullable()->after('notification_type');
            $table->foreignId('related_request_id')
                ->nullable()
                ->after('priority')
                ->constrained('repair_requests', 'request_id')
                ->nullOnDelete();
            $table->string('event_key', 150)->nullable()->after('related_request_id');
            $table->index(['user_id', 'announcement_id', 'is_read']);
            $table->unique(['user_id', 'event_key']);
        });
    }

    public function down(): void
    {
        Schema::table('notifications', function (Blueprint $table) {
            $table->dropIndex('notifications_user_id_announcement_id_is_read_index');
            $table->dropUnique('notifications_user_id_event_key_unique');
            $table->dropConstrainedForeignId('related_request_id');
            $table->dropColumn(['notification_type', 'priority', 'event_key']);
        });
    }
};
