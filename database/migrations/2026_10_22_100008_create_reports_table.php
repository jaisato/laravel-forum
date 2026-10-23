<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('post_id')->constrained()->cascadeOnDelete();
            // Null when the AI moderator raised the report.
            $table->foreignId('reporter_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('source', 10)->default('user');
            $table->string('reason', 20);
            $table->text('details')->nullable();
            $table->float('ai_confidence')->nullable();
            $table->string('status', 20)->default('open');
            $table->foreignId('resolved_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('resolved_at')->nullable();
            $table->text('resolution_note')->nullable();
            $table->timestamps();

            $table->index(['status', 'created_at']);
            $table->index('post_id');
        });

        // One open report per member and post, and one open AI report per post.
        // Partial unique indexes (PostgreSQL and SQLite) keep the report actions
        // idempotent even when two identical requests race each other.
        DB::statement("create unique index reports_open_member_unique on reports (post_id, reporter_id) where status = 'open' and reporter_id is not null");
        DB::statement("create unique index reports_open_ai_unique on reports (post_id) where status = 'open' and source = 'ai'");
    }

    public function down(): void
    {
        Schema::dropIfExists('reports');
    }
};
