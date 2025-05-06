<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Challenge extends Model
{
    use HasFactory;

    protected $fillable = [
        'title',
        'description',
        'instructions',
        'creator_id',
        'category_id',
        'difficulty',
        'is_public',
        'duration',
    ];

    /**
     * Get the user who created the challenge.
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'creator_id');
    }

    /**
     * Get the category of the challenge.
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    /**
     * Get the users who participate in the challenge.
     */
    public function participants(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'challenge_participations')
            ->withPivot('status', 'completed_at')
            ->withTimestamps();
    }

    /**
     * Get participations for this challenge.
     */
    public function participations(): HasMany
    {
        return $this->hasMany(ChallengeParticipation::class);
    }

    /**
     * Get media for this challenge.
     */
    public function media(): HasMany
    {
        return $this->hasMany(ChallengeMedia::class);
    }

    /**
     * Get active participants for this challenge.
     */
    public function activeParticipants()
    {
        return $this->participants()
            ->wherePivot('status', 'accepted')
            ->wherePivotNull('completed_at');
    }

    /**
     * Get completed participations for this challenge.
     */
    public function completedParticipations()
    {
        return $this->participations()
            ->where('status', 'completed');
    }

    /**
     * Get pending invitations for this challenge.
     */
    public function pendingInvitations()
    {
        return $this->participations()
            ->where('status', 'invited');
    }

    /**
     * Récupère le défi parent de ce défi (si c'est un clone)
     */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(Challenge::class, 'parent_challenge_id');
    }

    /**
     * Récupère les clones de ce défi
     */
    public function clones(): HasMany
    {
        return $this->hasMany(Challenge::class, 'parent_challenge_id');
    }
}