<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Laravel\Sanctum\HasApiTokens;
use Illuminate\Notifications\Notifiable;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable, HasApiTokens;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'avatar',
        'bio',
        'preferences',
        'is_premium',           // Ajouté
        'premium_until',        // Ajouté
        'subscription_plan',    // Ajouté
        'subscription_status', 
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'preferences' => 'array',
            'is_premium' => 'boolean',
            'premium_until' => 'datetime', 
        ];
    }

    /**
     * Détermine si l'utilisateur est administrateur
     * 
     * @return bool
     */
    public function isAdmin()
    {
        return (bool) $this->is_admin;
    }

    /**
     * Get the challenges created by the user.
     */
    public function createdChallenges(): HasMany
    {
        return $this->hasMany(Challenge::class, 'creator_id');
    }

    /**
     * Get the user's challenge participations.
     */
    public function participations(): HasMany
    {
        return $this->hasMany(ChallengeParticipation::class);
    }

    /**
     * Get the challenges in which the user participates.
     */
    public function participatingChallenges(): BelongsToMany
    {
        return $this->belongsToMany(Challenge::class, 'challenge_participations')
            ->withPivot('status', 'completed_at')
            ->withTimestamps();
    }

    /**
     * Amis où l'utilisateur a envoyé la demande.
     */
    public function friendsOfMine(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'friendships', 'user_id', 'friend_id')
            ->wherePivot('status', 'accepted')
            ->withPivot('id')
            ->withTimestamps();
    }

    /**
     * Amis où l'utilisateur a reçu la demande.
     */
    public function friendOf(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'friendships', 'friend_id', 'user_id')
            ->wherePivot('status', 'accepted')
            ->withPivot('id')
            ->withTimestamps();
    }

    /**
    * Retourne tous les amis (fusion des deux relations).
    */
    public function friends()
    {
        return $this->friendsOfMine->merge($this->friendOf);
    }

    /**
     * Get the user's pending friend requests.
     */
    public function pendingFriendRequests(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'friendships', 'friend_id', 'user_id')
            ->wherePivot('status', 'pending')
            ->withPivot('id') // Ajout de l'ID de la relation
            ->withTimestamps();
    }

    /**
     * Get the friend requests sent by the user.
     */
    public function sentFriendRequests(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'friendships', 'user_id', 'friend_id')
            ->wherePivot('status', 'pending')
            ->withPivot('id') // Ajouter l'ID du pivot
            ->withTimestamps();
    }

    /**
     * Get the media uploaded by the user.
     */
    public function media(): HasMany
    {
        return $this->hasMany(ChallengeMedia::class);
    }

    /**
     * Get invitations sent by this user.
     */
    public function sentInvitations(): HasMany
    {
        return $this->hasMany(ChallengeParticipation::class, 'invited_by');
    }

    /**
     * Get the user's active participations.
     */
    public function activeParticipations()
    {
        return $this->participations()
            ->where('status', 'accepted')
            ->whereNull('completed_at')
            ->whereNull('abandoned_at');
    }

    /**
     * Get the user's completed participations.
     */
    public function completedParticipations()
    {
        return $this->participations()
            ->where('status', 'completed');
    }

    /**
     * Get the user's pending invitations.
     */
    public function pendingInvitations()
    {
        return $this->participations()
            ->where('status', 'invited');
    }

    /**
     * Récupère les groupes de défis de l'utilisateur
     */
    public function challengeGroups(): BelongsToMany
    {
        return $this->belongsToMany(ChallengeGroup::class, 'challenge_group_user')
            ->withPivot('role')
            ->withTimestamps();
    }

    /**
     * Récupère les groupes de défis où l'utilisateur est administrateur
     */
    public function adminChallengeGroups(): BelongsToMany
    {
        return $this->challengeGroups()
            ->wherePivot('role', 'admin')
            ->orWhere('challenge_groups.creator_id', $this->id);
    }

    /**
     * Récupère les groupes de défis créés par l'utilisateur
     */
    public function createdChallengeGroups(): HasMany
    {
        return $this->hasMany(ChallengeGroup::class, 'creator_id');
    }

    /**
     * Récupère l'abonnement actif de l'utilisateur
     */
    public function subscription(): HasOne
    {
        return $this->hasOne(Subscription::class)->latest();
    }

    /**
     * Vérifie si l'utilisateur a un abonnement premium actif
     */
    public function isPremium(): bool
    {
        // Vérifier si l'utilisateur a un abonnement actif ou est encore en période premium
        return $this->is_premium && ($this->premium_until === null || $this->premium_until->isFuture());
    }

    /**
     * Obtient le niveau d'abonnement de l'utilisateur
     */
    public function getPremiumLevel(): string
    {
        if (!$this->isPremium()) {
            return 'free';
        }
        
        return $this->subscription_plan ?? 'premium';
    }

    /**
     * Obtient le nombre de jours restants de l'abonnement
     */
    public function getRemainingDays(): int
    {
        if (!$this->isPremium() || $this->premium_until === null) {
            return 0;
        }
        
        return now()->diffInDays($this->premium_until, false);
    }

    public function subscriptions()
    {
        return $this->hasMany(Subscription::class);
    }
}