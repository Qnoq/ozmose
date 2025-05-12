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
        Schema::create('truth_or_dare_rounds', function (Blueprint $table) {
            $table->id();
            $table->foreignId('session_id')->constrained('truth_or_dare_sessions')->onDelete('cascade');
            $table->foreignId('participant_id')->constrained('truth_or_dare_participants')->onDelete('cascade');
            $table->foreignId('question_id')->constrained('truth_or_dare_questions')->onDelete('cascade');
            $table->enum('choice', ['truth', 'dare']);
            $table->enum('status', ['pending', 'completed', 'skipped'])->default('pending');
            $table->text('response')->nullable(); // Pour les vérités
            $table->foreignId('proof_media_id')->nullable()->constrained('challenge_media')->nullOnDelete();
            $table->integer('rating')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('truth_or_dare_rounds');
    }
};
