<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ChallengeStageParticipation extends Model
{
    protected $fillable = [
        'participation_id',
        'stage_id',
        'status',
        'completed_at',
        'proof_media_id',
        'notes'
    ];
    
    protected $casts = [
        'completed_at' => 'datetime',
    ];
    
    public function participation()
    {
        return $this->belongsTo(ChallengeParticipation::class);
    }
    
    public function stage()
    {
        return $this->belongsTo(ChallengeStage::class);
    }
    
    public function proofMedia()
    {
        return $this->belongsTo(ChallengeMedia::class, 'proof_media_id');
    }
}
