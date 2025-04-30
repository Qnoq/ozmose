<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Subscription extends Model
{
    // Autorise l'attribution de masse sur ces champs
    protected $fillable = [
        'user_id',
        'stripe_id',
        'status',
        'plan_id',
        'amount',
        'currency',
        'interval',
        'trial_ends_at',
        'ends_at'
    ];

    // Relation : Un abonnement appartient à un utilisateur
    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
