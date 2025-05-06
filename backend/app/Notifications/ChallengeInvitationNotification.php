<?php

namespace App\Notifications;

use App\Models\User;
use App\Models\Challenge;
use App\Models\ChallengeParticipation;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ChallengeInvitationNotification extends Notification implements ShouldQueue
{
    use Queueable;

    protected $sender;
    protected $challenge;
    protected $participation;
    protected $message;

    /**
     * Create a new notification instance.
     */
    public function __construct(User $sender, Challenge $challenge, ChallengeParticipation $participation, ?string $message = null)
    {
        $this->sender = $sender;
        $this->challenge = $challenge;
        $this->participation = $participation;
        $this->message = $message;
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
            ->subject($this->sender->name . ' vous invite à un défi')
            ->greeting('Bonjour ' . $notifiable->name . ' !')
            ->line($this->sender->name . ' vous invite à participer au défi : ' . $this->challenge->title)
            ->line('Difficulté : ' . $this->challenge->difficulty)
            ->action('Voir l\'invitation', url('/defis/invitations'))
            ->line('Merci d\'utiliser Ozmose !');
            
        if ($this->message) {
            $mail->line('Message : ' . $this->message);
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
            'type' => 'challenge_invitation',
            'sender_id' => $this->sender->id,
            'sender_name' => $this->sender->name,
            'sender_avatar' => $this->sender->avatar,
            'challenge_id' => $this->challenge->id,
            'challenge_title' => $this->challenge->title,
            'participation_id' => $this->participation->id,
            'message' => $this->sender->name . ' vous invite à participer au défi : ' . $this->challenge->title,
            'custom_message' => $this->message
        ];
    }
}