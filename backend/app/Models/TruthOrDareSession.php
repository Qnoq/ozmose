<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class TruthOrDareSession extends Model
{
    use HasFactory;
    
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

    protected static function boot()
    {
        parent::boot();
        
        static::creating(function ($session) {
            if (!$session->join_code) {
                $session->join_code = static::generateUniqueCode();
            }
        });
    }

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

    private static function generateUniqueCode(): string
    {
        do {
            $code = strtoupper(substr(str_shuffle(md5(time() . rand())), 0, 6));
        } while (static::where('join_code', $code)->exists());
        
        return $code;
    }
}