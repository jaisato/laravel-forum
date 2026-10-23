<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('threads', function (Blueprint $table) {
            $table->id();
            $table->foreignId('category_id')->constrained()->restrictOnDelete();
            // Accounts are soft deleted and anonymised, never removed, so
            // authorship rows can always be resolved.
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->string('title', 150);
            $table->string('slug', 100)->unique();
            $table->string('status', 20)->default('published');
            $table->boolean('is_pinned')->default(false);
            $table->boolean('is_locked')->default(false);
            // Pointers into `posts`, kept by the forum actions. They carry no
            // foreign key: posts reference threads too, and SQLite cannot add
            // a constraint to an existing table to close the cycle.
            $table->unsignedBigInteger('first_post_id')->nullable()->index();
            $table->unsignedBigInteger('last_post_id')->nullable();
            $table->unsignedBigInteger('solution_post_id')->nullable();
            $table->timestamp('last_posted_at')->nullable();
            $table->unsignedInteger('replies_count')->default(0);
            $table->unsignedInteger('views_count')->default(0);
            $table->text('ai_summary')->nullable();
            $table->timestamp('ai_summary_generated_at')->nullable();
            $table->unsignedInteger('ai_summary_replies_count')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['category_id', 'is_pinned', 'last_posted_at']);
            $table->index(['status', 'last_posted_at']);
            $table->index(['user_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('threads');
    }
};
