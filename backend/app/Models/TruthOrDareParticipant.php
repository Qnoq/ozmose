<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TruthOrDareParticipant extends Model
{
    protected $fillable = [
        'session_id',
        'user_id',
        'guest_name',
        'guest_avatar',
        'status',
        'truths_answered',
        'dares_completed',
        'skips_used',
        'turn_order'
    ];

    public function session(): BelongsTo
    {
        return $this->belongsTo(TruthOrDareSession::class, 'session_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class)->nullable();
    }

    public function getDisplayNameAttribute(): string
    {
        return $this->user ? $this->user->name : $this->guest_name;
    }

    public function getAvatarAttribute(): string
    {
        if ($this->user && $this->user->avatar) {
            return $this->user->avatar;
        }
        return $this->guest_avatar ?? '😀'; // Emoji par défaut
    }

    public function isGuest(): bool
    {
        return $this->user_id === null;
    }

    public function isHost(): bool
    {
        return $this->user_id === $this->session->creator_id;
    }
}