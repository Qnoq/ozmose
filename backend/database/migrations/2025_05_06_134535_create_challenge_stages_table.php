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
        // 1. Table pour les défis multi-étapes
        Schema::create('challenge_stages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('challenge_id')->constrained()->onDelete('cascade');
            $table->string('title');
            $table->text('description');
            $table->text('instructions');
            $table->integer('order')->default(1);
            $table->integer('duration')->nullable();
            $table->boolean('requires_proof')->default(false);
            $table->timestamps();
        });

        // 2. Table pour les participations aux étapes
        Schema::create('challenge_stage_participations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('participation_id')->constrained('challenge_participations')->onDelete('cascade');
            $table->foreignId('stage_id')->constrained('challenge_stages')->onDelete('cascade');
            $table->enum('status', ['locked', 'active', 'completed', 'skipped'])->default('locked');
            $table->timestamp('completed_at')->nullable();
            $table->foreignId('proof_media_id')->nullable()->constrained('challenge_media')->nullOnDelete();
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('challenge_stages');
        Schema::dropIfExists('challenge_stage_participations');
    }
};
