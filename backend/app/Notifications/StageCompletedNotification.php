<?php

namespace App\Notifications;

use App\Models\User;
use App\Models\Challenge;
use App\Models\ChallengeStage;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class StageCompletedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    protected $user;
    protected $challenge;
    protected $stage;
    protected $hasProof;
    protected $isLastStage;

    /**
     * Create a new notification instance.
     */
    public function __construct(User $user, Challenge $challenge, ChallengeStage $stage, bool $hasProof = false, bool $isLastStage = false)
    {
        $this->user = $user;
        $this->challenge = $challenge;
        $this->stage = $stage;
        $this->hasProof = $hasProof;
        $this->isLastStage = $isLastStage;
    }

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail(object $notifiable): MailMessage
    {
        $mail = (new MailMessage)
            ->subject($this->user->name . ' a complété une étape du défi "' . $this->challenge->title . '"')
            ->greeting('Bonjour ' . $notifiable->name . ' !')
            ->line($this->user->name . ' a complété l\'étape "' . $this->stage->title . '" du défi "' . $this->challenge->title . '"')
            ->action('Voir le défi', url('/defis/' . $this->challenge->id))
            ->line('Merci d\'utiliser Ozmose !');
            
        if ($this->hasProof) {
            $mail->line('Une preuve de réalisation a été ajoutée - consultez-la dès maintenant !');
        }
        
        if ($this->isLastStage) {
            $mail->line('C\'était la dernière étape du défi, qui est maintenant complété !');
        }
        
        return $mail;
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'stage_completed',
            'user_id' => $this->user->id,
            'user_name' => $this->user->name,
            'user_avatar' => $this->user->avatar,
            'challenge_id' => $this->challenge->id,
            'challenge_title' => $this->challenge->title,
            'stage_id' => $this->stage->id,
            'stage_title' => $this->stage->title,
            'stage_order' => $this->stage->order,
            'has_proof' => $this->hasProof,
            'is_last_stage' => $this->isLastStage,
            'message' => $this->user->name . ' a complété l\'étape "' . $this->stage->title . '" du défi "' . $this->challenge->title . '"'
        ];
    }
}