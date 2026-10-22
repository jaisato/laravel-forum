<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('categories', function (Blueprint $table) {
            $table->id();
            $table->string('name', 60);
            $table->string('slug', 80)->unique();
            $table->string('description', 300)->nullable();
            $table->string('color', 7)->default('#6366f1');
            $table->string('icon', 40)->nullable();
            $table->unsignedInteger('position')->default(0);
            // Locked categories (announcements) only accept threads from staff.
            $table->boolean('is_locked')->default(false);
            $table->unsignedInteger('threads_count')->default(0);
            $table->unsignedInteger('posts_count')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('categories');
    }
};
