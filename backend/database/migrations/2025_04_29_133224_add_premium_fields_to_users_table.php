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
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('is_premium')->default(false);
            $table->timestamp('premium_until')->nullable();
            $table->string('subscription_id')->nullable();
            $table->string('subscription_status')->nullable();
            $table->string('subscription_plan')->nullable(); // basic, standard, premium
            $table->timestamp('trial_ends_at')->nullable();  // Pour les périodes d'essai
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            //
        });
    }
};
