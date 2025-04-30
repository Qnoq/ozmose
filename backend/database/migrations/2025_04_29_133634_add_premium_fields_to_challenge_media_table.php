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
        Schema::table('challenge_media', function (Blueprint $table) {
            $table->boolean('high_quality')->default(false); // Qualité originale conservée pour premium
            $table->boolean('in_compilation')->default(false); // Pour créer des compilations
            $table->foreignId('compilation_id')->nullable()->constrained('challenge_media')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('challenge_media', function (Blueprint $table) {
            $table->dropColumn('high_quality');
            $table->dropColumn('in_compilation');
            $table->dropColumn('compilation_id');
        });
    }
};
