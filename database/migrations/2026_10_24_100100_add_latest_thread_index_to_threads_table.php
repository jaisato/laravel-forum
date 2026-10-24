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
        Schema::table('threads', function (Blueprint $table) {
            // Each category's public thread with the latest activity
            // (Category::withLatestThread()), read from the newest end of this
            // index, one category at a time.
            $table->index(['category_id', 'status', 'last_posted_at', 'id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('threads', function (Blueprint $table) {
            $table->dropIndex(['category_id', 'status', 'last_posted_at', 'id']);
        });
    }
};
