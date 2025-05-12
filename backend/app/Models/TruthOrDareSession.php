<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class TruthOrDareSession extends Model
{
    protected $fillable = [
        'creator_id',
        'name',
        'description',
        'intensity',
        'is_public',
        'is_active',
        'join_code',
        'max_participants',
        'premium_only'
    ];

    protected $casts = [
        'is_public' => 'boolean',
        'is_active' => 'boolean',
        'premium_only' => 'boolean',
    ];

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'creator_id');
    }

    public function participants(): HasMany
    {
        return $this->hasMany(TruthOrDareParticipant::class, 'session_id');
    }

    public function rounds(): HasMany
    {
        return $this->hasMany(TruthOrDareRound::class, 'session_id');
    }

    public function activeParticipants(): HasMany
    {
        return $this->participants()->where('status', 'active');
    }

    public function generateJoinCode(): string
    {
        do {
            $code = strtoupper(substr(md5(uniqid()), 0, 6));
        } while (self::where('join_code', $code)->exists());
        
        $this->join_code = $code;
        $this->save();
        
        return $code;
    }
}