<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Ajoute les index nécessaires pour optimiser les performances
     */
    public function up(): void
    {
        // Index pour la table challenges
        Schema::table('challenges', function (Blueprint $table) {
            // Index pour la recherche de défis par créateur
            $table->index('creator_id', 'ch_creator_idx');
            
            // Index pour la recherche de défis par catégorie
            $table->index('category_id', 'ch_category_idx');
            
            // Index combiné pour les requêtes de défis publics par catégorie
            $table->index(['is_public', 'category_id'], 'ch_public_cat_idx');
            
            // Index pour la recherche de défis par difficulté
            $table->index('difficulty', 'ch_difficulty_idx');
            
            // Index pour les défis premium
            $table->index(['premium_only', 'is_public'], 'ch_premium_public_idx');
            
            // Index pour les défis programmés
            $table->index(['scheduled', 'scheduled_for'], 'ch_scheduled_idx');
        });

        // Index pour la table challenge_participations
        Schema::table('challenge_participations', function (Blueprint $table) {
            // Index pour retrouver les participations d'un utilisateur
            $table->index(['user_id', 'status'], 'cp_user_status_idx');
            
            // Index pour retrouver les participations à un défi
            $table->index(['challenge_id', 'status'], 'cp_challenge_status_idx');
            
            // Index pour les invitations en attente
            $table->index(['user_id', 'status', 'invited_by'], 'cp_invite_idx');
            
            // Index pour les défis actifs d'un utilisateur (simplifié)
            $table->index(['user_id', 'status'], 'cp_user_active_idx');
            
            // Ajout d'un index séparé pour les dates de complétion
            $table->index('completed_at', 'cp_completed_idx');
            $table->index('abandoned_at', 'cp_abandoned_idx');
            
            // Index pour les défis complétés
            $table->index(['status', 'completed_at'], 'cp_status_completed_idx');
        });

        // Index pour la table friendships
        Schema::table('friendships', function (Blueprint $table) {
            // Index pour la recherche bidirectionnelle des amitiés
            $table->index(['user_id', 'status'], 'fs_user_status_idx');
            $table->index(['friend_id', 'status'], 'fs_friend_status_idx');
        });

        // Index pour la table challenge_media
        Schema::table('challenge_media', function (Blueprint $table) {
            // Index pour retrouver les médias d'un utilisateur
            $table->index(['user_id', 'type'], 'cm_user_type_idx');
            
            // Index pour retrouver les médias d'un défi
            $table->index(['challenge_id', 'type'], 'cm_challenge_type_idx');
            
            // Index pour retrouver les médias d'une participation
            $table->index(['participation_id', 'type'], 'cm_participation_type_idx');
            
            // Index pour les compilations (simplifié)
            $table->index(['user_id', 'in_compilation'], 'cm_compilation_user_idx');
            $table->index('compilation_id', 'cm_compilation_id_idx');
        });

        // Index pour la table challenge_groups
        Schema::table('challenge_groups', function (Blueprint $table) {
            // Index pour les groupes créés par un utilisateur
            $table->index('creator_id', 'cg_creator_idx');
            
            // Index pour les groupes premium
            $table->index('premium_only', 'cg_premium_idx');
        });

        // Index pour la table challenge_group_user
        Schema::table('challenge_group_user', function (Blueprint $table) {
            // Index pour retrouver les groupes d'un utilisateur
            $table->index(['user_id', 'role'], 'cgu_user_role_idx');
            
            // Index pour retrouver les membres d'un groupe
            $table->index(['challenge_group_id', 'role'], 'cgu_group_role_idx');
        });

        // Index pour la table categories
        Schema::table('categories', function (Blueprint $table) {
            // Index pour la recherche par nom de catégorie
            $table->index('name', 'cat_name_idx');
        });

        // Index pour la table users
        Schema::table('users', function (Blueprint $table) {
            // Index pour la recherche d'utilisateurs par nom
            $table->index('name', 'usr_name_idx');
            
            // Index pour les utilisateurs premium
            $table->index(['is_premium', 'premium_until'], 'usr_premium_idx');
            
            // Index pour les utilisateurs par plan d'abonnement
            $table->index(['subscription_plan', 'subscription_status'], 'usr_subscription_idx');
        });

        // Index pour la table subscriptions
        Schema::table('subscriptions', function (Blueprint $table) {
            // Index pour les abonnements par utilisateur
            $table->index(['user_id', 'status'], 'sub_user_idx');
            
            // Index pour les abonnements par plan
            $table->index(['plan_id', 'status'], 'sub_plan_idx');
            
            // Index pour les abonnements qui expirent bientôt
            $table->index(['status', 'ends_at'], 'sub_expiry_idx');
        });
    }

    /**
     * Reverse the migrations.
     * Supprime les index ajoutés
     */
    public function down(): void
    {
        // Supprimer les index de la table challenges
        Schema::table('challenges', function (Blueprint $table) {
            $table->dropIndex('ch_creator_idx');
            $table->dropIndex('ch_category_idx');
            $table->dropIndex('ch_public_cat_idx');
            $table->dropIndex('ch_difficulty_idx');
            $table->dropIndex('ch_premium_public_idx');
            $table->dropIndex('ch_scheduled_idx');
        });

        // Supprimer les index de la table challenge_participations
        Schema::table('challenge_participations', function (Blueprint $table) {
            $table->dropIndex('cp_user_status_idx');
            $table->dropIndex('cp_challenge_status_idx');
            $table->dropIndex('cp_invite_idx');
            $table->dropIndex('cp_user_active_idx');
            $table->dropIndex('cp_completed_idx');
            $table->dropIndex('cp_abandoned_idx');
            $table->dropIndex('cp_status_completed_idx');
        });

        // Supprimer les index de la table friendships
        Schema::table('friendships', function (Blueprint $table) {
            $table->dropIndex('fs_user_status_idx');
            $table->dropIndex('fs_friend_status_idx');
        });

        // Supprimer les index de la table challenge_media
        Schema::table('challenge_media', function (Blueprint $table) {
            $table->dropIndex('cm_user_type_idx');
            $table->dropIndex('cm_challenge_type_idx');
            $table->dropIndex('cm_participation_type_idx');
            $table->dropIndex('cm_compilation_user_idx');
            $table->dropIndex('cm_compilation_id_idx');
        });

        // Supprimer les index de la table challenge_groups
        Schema::table('challenge_groups', function (Blueprint $table) {
            $table->dropIndex('cg_creator_idx');
            $table->dropIndex('cg_premium_idx');
        });

        // Supprimer les index de la table challenge_group_user
        Schema::table('challenge_group_user', function (Blueprint $table) {
            $table->dropIndex('cgu_user_role_idx');
            $table->dropIndex('cgu_group_role_idx');
        });

        // Supprimer les index de la table categories
        Schema::table('categories', function (Blueprint $table) {
            $table->dropIndex('cat_name_idx');
        });

        // Supprimer les index de la table users
        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex('usr_name_idx');
            $table->dropIndex('usr_premium_idx');
            $table->dropIndex('usr_subscription_idx');
        });

        // Supprimer les index de la table subscriptions
        Schema::table('subscriptions', function (Blueprint $table) {
            $table->dropIndex('sub_user_idx');
            $table->dropIndex('sub_plan_idx');
            $table->dropIndex('sub_expiry_idx');
        });
    }
};