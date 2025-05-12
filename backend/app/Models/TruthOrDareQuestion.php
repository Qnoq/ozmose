<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class TruthOrDareQuestion extends Model
{
    use HasFactory;
    
    protected $fillable = [
        'creator_id',
        'category_id',
        'type',
        'content',
        'intensity',
        'is_public',
        'is_premium',
        'is_official'
    ];

    protected $casts = [
        'is_public' => 'boolean',
        'is_premium' => 'boolean',
        'is_official' => 'boolean',
        'rating' => 'float',
    ];

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'creator_id');
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function rounds(): HasMany
    {
        return $this->hasMany(TruthOrDareRound::class, 'question_id');
    }

    public function incrementUsage(): void
    {
        $this->increment('times_used');
    }

    public function updateRating(): void
    {
        $avgRating = $this->rounds()
            ->whereNotNull('rating')
            ->avg('rating');
            
        $this->update(['rating' => $avgRating]);
    }
}