<?php

namespace App\Services;

use App\Models\TruthOrDareSession;

class TruthOrDareService
{
    /**
     * Obtenir les statistiques d'une session
     */
    public function getSessionStats(TruthOrDareSession $session): array
    {
        info('getSessionStats');
        return [
            'total_rounds' => $session->rounds()->count(),
            'completed_rounds' => $session->rounds()->where('status', 'completed')->count(),
            'skipped_rounds' => $session->rounds()->where('status', 'skipped')->count(),
            'participants' => $session->participants()
                ->with('user:id,name')
                ->get()
                ->map(function ($participant) {
                    return [
                        'id' => $participant->id,
                        'name' => $participant->display_name,
                        'avatar' => $participant->avatar,
                        'is_guest' => $participant->isGuest(),
                        'truths_answered' => $participant->truths_answered,
                        'dares_completed' => $participant->dares_completed,
                        'skips_used' => $participant->skips_used,
                        'total_score' => $participant->truths_answered + $participant->dares_completed * 2
                    ];
                })
                ->sortByDesc('total_score')
                ->values()
                ->toArray()
        ];
    }
}