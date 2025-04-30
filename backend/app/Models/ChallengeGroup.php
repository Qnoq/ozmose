<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class ChallengeGroup extends Model
{
    use HasFactory;

    /**
     * Les attributs qui sont mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'description',
        'creator_id',
        'premium_only',
        'max_members',
    ];

    /**
     * Les attributs qui doivent être castés.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'premium_only' => 'boolean',
        'max_members' => 'integer',
    ];

    /**
     * Récupère le créateur du groupe.
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'creator_id');
    }

    /**
     * Récupère les membres du groupe.
     */
    public function members(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'challenge_group_user')
            ->withPivot('role')
            ->withTimestamps();
    }

    /**
     * Récupère les défis du groupe.
     */
    public function challenges(): BelongsToMany
    {
        return $this->belongsToMany(Challenge::class, 'challenge_group_challenge')
            ->withTimestamps();
    }

    /**
     * Récupère les administrateurs du groupe.
     */
    public function admins()
    {
        return $this->members()
            ->wherePivot('role', 'admin')
            ->orWhere('users.id', $this->creator_id);
    }

    /**
     * Vérifier si un utilisateur est membre du groupe.
     *
     * @param int $userId
     * @return bool
     */
    public function isMember(int $userId): bool
    {
        return $this->members()->where('users.id', $userId)->exists();
    }

    /**
     * Vérifier si un utilisateur est administrateur du groupe.
     *
     * @param int $userId
     * @return bool
     */
    public function isAdmin(int $userId): bool
    {
        if ($this->creator_id === $userId) {
            return true;
        }

        return $this->members()
            ->where('users.id', $userId)
            ->wherePivot('role', 'admin')
            ->exists();
    }

    /**
     * Vérifier si le groupe a atteint sa limite de membres.
     *
     * @return bool
     */
    public function isAtMemberLimit(): bool
    {
        return $this->members()->count() >= $this->max_members;
    }

    /**
     * Obtenir le nombre de places restantes dans le groupe.
     *
     * @return int
     */
    public function getRemainingSlots(): int
    {
        $currentCount = $this->members()->count();
        return max(0, $this->max_members - $currentCount);
    }
}