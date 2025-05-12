<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class TruthOrDareRound extends Model
{
    use HasFactory;

    protected $fillable = [
        'session_id',
        'participant_id',
        'question_id',
        'choice',
        'response',
        'proof_media_id',
        'rating',
        'is_completed',
        'is_skipped',
    ];
}
