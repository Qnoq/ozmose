<?php

namespace App\Notifications;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class FriendRequestAcceptedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    protected $sender;

    /**
     * Create a new notification instance.
     */
    public function __construct(User $sender)
    {
        $this->sender = $sender;
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
        return (new MailMessage)
            ->subject($this->sender->name . ' a accepté votre demande d\'amitié')
            ->greeting('Bonjour ' . $notifiable->name . ' !')
            ->line($this->sender->name . ' a accepté votre demande d\'amitié.')
            ->action('Voir votre profil', url('/profil/amis'))
            ->line('Merci d\'utiliser Ozmose !');
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'friend_request_accepted',
            'sender_id' => $this->sender->id,
            'sender_name' => $this->sender->name,
            'sender_avatar' => $this->sender->avatar,
            'message' => $this->sender->name . ' a accepté votre demande d\'amitié.'
        ];
    }
}