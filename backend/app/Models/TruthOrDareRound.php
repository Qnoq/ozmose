<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TruthOrDareRound extends Model
{
    protected $fillable = [
        'session_id',
        'participant_id',
        'question_id',
        'choice',
        'status',
        'response',
        'proof_media_id',
        'rating'
    ];

    /**
     * Relation avec la session
     */
    public function session(): BelongsTo
    {
        return $this->belongsTo(TruthOrDareSession::class, 'session_id');
    }

    /**
     * Relation avec le participant
     */
    public function participant(): BelongsTo
    {
        return $this->belongsTo(TruthOrDareParticipant::class, 'participant_id');
    }

    /**
     * Relation avec la question
     */
    public function question(): BelongsTo
    {
        return $this->belongsTo(TruthOrDareQuestion::class, 'question_id');
    }

    /**
     * Relation avec le média de preuve (si applicable)
     */
    public function proofMedia(): BelongsTo
    {
        return $this->belongsTo(ChallengeMedia::class, 'proof_media_id');
    }
}
