<?php

namespace App\Notifications;

use App\Models\User;
use App\Models\Challenge;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ChallengeCompletedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    protected $user;
    protected $challenge;
    protected $hasProof;

    /**
     * Create a new notification instance.
     */
    public function __construct(User $user, Challenge $challenge, bool $hasProof = false)
    {
        $this->user = $user;
        $this->challenge = $challenge;
        $this->hasProof = $hasProof;
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
            ->subject($this->user->name . ' a complété un défi')
            ->greeting('Bonjour ' . $notifiable->name . ' !')
            ->line($this->user->name . ' a complété le défi : ' . $this->challenge->title)
            ->action('Voir le défi', url('/defis/' . $this->challenge->id))
            ->line('Merci d\'utiliser Ozmose !');
            
        if ($this->hasProof) {
            $mail->line('Une preuve de réalisation a été ajoutée - consultez-la dès maintenant !');
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
            'type' => 'challenge_completed',
            'user_id' => $this->user->id,
            'user_name' => $this->user->name,
            'user_avatar' => $this->user->avatar,
            'challenge_id' => $this->challenge->id,
            'challenge_title' => $this->challenge->title,
            'has_proof' => $this->hasProof,
            'message' => $this->user->name . ' a complété le défi : ' . $this->challenge->title
        ];
    }
}