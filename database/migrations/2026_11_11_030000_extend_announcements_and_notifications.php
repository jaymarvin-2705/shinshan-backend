<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('announcements', function (Blueprint $table) {
            $table->string('category', 40)->default('general')->after('content');
            $table->string('target_audience', 20)->default('all')->after('category');
            $table->string('status', 20)->default('draft')->after('target_audience');
            $table->string('attachment_path')->nullable()->after('status');
            $table->timestamp('publish_at')->nullable()->after('attachment_path');
            $table->timestamp('published_at')->nullable()->after('publish_at');
            $table->timestamp('expires_at')->nullable()->after('published_at');
            $table->index(['status', 'publish_at']);
            $table->index(['target_audience', 'status', 'published_at']);
        });

        Schema::table('notifications', function (Blueprint $table) {
            $table->foreignId('announcement_id')
                ->nullable()
                ->after('user_id')
                ->constrained('announcements', 'announcement_id')
                ->cascadeOnDelete();
            $table->timestamp('read_at')->nullable()->after('is_read');
            $table->unique(['announcement_id', 'user_id']);
            $table->index(['user_id', 'is_read']);
        });
    }

    public function down(): void
    {
        Schema::table('notifications', function (Blueprint $table) {
            $table->dropUnique('notifications_announcement_id_user_id_unique');
            $table->dropIndex('notifications_user_id_is_read_index');
            $table->dropConstrainedForeignId('announcement_id');
            $table->dropColumn('read_at');
        });

        Schema::table('announcements', function (Blueprint $table) {
            $table->dropIndex('announcements_status_publish_at_index');
            $table->dropIndex('announcements_target_audience_status_published_at_index');
            $table->dropColumn([
                'category',
                'target_audience',
                'status',
                'attachment_path',
                'publish_at',
                'published_at',
                'expires_at',
            ]);
        });
    }
};
