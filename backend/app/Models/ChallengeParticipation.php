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

    /**
     * Récupère les participations aux étapes pour un défi multi-étapes
     */
    public function stageParticipations(): HasMany
    {
        return $this->hasMany(ChallengeStageParticipation::class, 'participation_id');
    }
    
    /**
     * Vérifie si toutes les étapes sont complétées
     * 
     * @return bool
     */
    public function areAllStagesCompleted(): bool
    {
        if (!$this->challenge->multi_stage) {
            return false;
        }
        
        $stagesCount = $this->challenge->stages()->count();
        $completedCount = $this->stageParticipations()
            ->where('status', 'completed')
            ->count();
            
        return $stagesCount > 0 && $stagesCount === $completedCount;
    }
    
    /**
     * Récupère l'étape active actuelle
     * 
     * @return ChallengeStageParticipation|null
     */
    public function getActiveStage()
    {
        if (!$this->challenge->multi_stage) {
            return null;
        }
        
        return $this->stageParticipations()
            ->where('status', 'active')
            ->first();
    }
    
    /**
     * Récupère la prochaine étape (verrouillée)
     * 
     * @return ChallengeStageParticipation|null
     */
    public function getNextStage()
    {
        if (!$this->challenge->multi_stage) {
            return null;
        }
        
        $activeStage = $this->getActiveStage();
        
        if (!$activeStage) {
            return null;
        }
        
        $activeStageOrder = $activeStage->stage->order;
        
        // Trouver l'étape avec l'ordre immédiatement supérieur
        $nextStage = $this->challenge->stages()
            ->where('order', '>', $activeStageOrder)
            ->orderBy('order')
            ->first();
            
        if (!$nextStage) {
            return null;
        }
        
        return $this->stageParticipations()
            ->where('stage_id', $nextStage->id)
            ->first();
    }
    
    /**
     * Obtient le pourcentage de progression dans les étapes
     * 
     * @return int
     */
    public function getStagesProgressPercentage(): int
    {
        if (!$this->challenge->multi_stage) {
            return 0;
        }
        
        $stagesCount = $this->challenge->stages()->count();
        
        if ($stagesCount === 0) {
            return 0;
        }
        
        $completedCount = $this->stageParticipations()
            ->where('status', 'completed')
            ->count();
            
        return round(($completedCount / $stagesCount) * 100);
    }
}