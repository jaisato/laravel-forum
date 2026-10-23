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
        Schema::table('notifications', function (Blueprint $table) {
            // The post a forum notification is about (ForumDatabaseChannel):
            // once the post stops being public, the notification no longer
            // shows what it said (UserNotification::isWithheldFrom()).
            $table->foreignId('post_id')->nullable()->after('actor_id')->index()->constrained()->cascadeOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('notifications', function (Blueprint $table) {
            $table->dropConstrainedForeignId('post_id');
        });
    }
};
