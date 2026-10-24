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
            // The answer that solved the thread until the AI held it for
            // review (HidePost): approving it solves the thread again
            // (ApprovePost), unless its author chose another one meanwhile.
            $table->unsignedBigInteger('held_solution_post_id')->nullable()->after('solution_post_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('threads', function (Blueprint $table) {
            $table->dropColumn('held_solution_post_id');
        });
    }
};
