<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ChallengeStage extends Model
{
    protected $fillable = [
        'challenge_id',
        'title',
        'description',
        'instructions',
        'order',
        'duration',
        'requires_proof'
    ];
    
    public function challenge()
    {
        return $this->belongsTo(Challenge::class);
    }
    
    public function participations()
    {
        return $this->hasMany(ChallengeStageParticipation::class, 'stage_id');
    }
}
