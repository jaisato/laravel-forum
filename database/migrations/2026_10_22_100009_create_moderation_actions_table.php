<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Append-only audit log: rows are never updated, hence no updated_at.
        Schema::create('moderation_actions', function (Blueprint $table) {
            $table->id();
            // Null when the system acted on its own (e.g. the AI moderator).
            $table->foreignId('moderator_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('action', 40);
            $table->morphs('subject');
            $table->text('reason')->nullable();
            $table->json('meta')->nullable();
            $table->timestamp('created_at')->nullable();

            $table->index('moderator_id');
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('moderation_actions');
    }
};
