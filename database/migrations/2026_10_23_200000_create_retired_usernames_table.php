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
        Schema::create('retired_usernames', function (Blueprint $table) {
            $table->id();
            // A username its member left behind, by changing it or erasing
            // the account: old @mentions and links still point at it, so
            // nobody else may answer to it (RetiredUsername).
            $table->string('username', 30)->unique();
            // Who may take it back; an erased account keeps it for good.
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->timestamp('retired_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('retired_usernames');
    }
};
