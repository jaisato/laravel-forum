<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('thread_reads', function (Blueprint $table) {
            $table->id();
            $table->foreignId('thread_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            // Unread state compares post ids, which only grow: a timestamp has
            // second precision and would mark a reply written in the same
            // second as the visit as already read. The date is kept for display.
            $table->unsignedBigInteger('last_read_post_id')->default(0);
            $table->timestamp('last_read_at');

            $table->unique(['thread_id', 'user_id']);
            $table->index('user_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('thread_reads');
    }
};
