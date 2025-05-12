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
        Schema::create('truth_or_dare_questions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('creator_id')->constrained('users')->onDelete('cascade');
            $table->foreignId('category_id')->nullable()->constrained()->nullOnDelete();
            $table->enum('type', ['truth', 'dare']);
            $table->text('content');
            $table->enum('intensity', ['soft', 'spicy', 'hot'])->default('soft');
            $table->boolean('is_public')->default(false);
            $table->boolean('is_premium')->default(false);
            $table->integer('times_used')->default(0);
            $table->float('rating')->nullable();
            $table->boolean('is_official')->default(false); // Questions créées par Ozmose
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('truth_or_dare_questions');
    }
};
