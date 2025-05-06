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
        Schema::table('challenges', function (Blueprint $table) {
            $table->unsignedBigInteger('parent_challenge_id')->nullable()->after('creator_id');
            $table->foreign('parent_challenge_id')->references('id')->on('challenges')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('challenges', function (Blueprint $table) {
            $table->dropForeign(['parent_challenge_id']);
            $table->dropColumn('parent_challenge_id');
        });
    }
};
