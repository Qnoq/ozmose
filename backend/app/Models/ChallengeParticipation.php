<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ChallengeParticipation extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'challenge_id',
        'status',
        'completed_at',
        'abandoned_at',
        'feedback_at',
        'proof_media_id',
        'notes',           
        'feedback',        
        'rating',          
        'invited_by',      
        'invitation_message', 
        'started_at', 
    ];

    protected $casts = [
        'completed_at' => 'datetime',
        'abandoned_at' => 'datetime',
        'feedback_at' => 'datetime',
        'started_at' => 'datetime',
    ];

    public function challenge(): BelongsTo
    {
        return $this->belongsTo(Challenge::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function inviter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'invited_by');
    }

    public function proofMedia(): BelongsTo
    {
        return $this->belongsTo(ChallengeMedia::class, 'proof_media_id');
    }

    public function media(): HasMany
    {
        return $this->hasMany(ChallengeMedia::class, 'participation_id');
    }
}