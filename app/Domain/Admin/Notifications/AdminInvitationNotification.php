<?php

namespace App\Domain\Admin\Notifications;

use App\Domain\Admin\Models\AdminInvitation;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use SensitiveParameter;

/**
 * E-mail carrying the personal activation link. It is deliberately not
 * queueable: the link contains the raw token, which must not be stored.
 */
class AdminInvitationNotification extends Notification
{
    public function __construct(
        public readonly AdminInvitation $invitation,
        #[SensitiveParameter] public readonly string $url,
    ) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $expiresAt = $this->invitation->expires_at
            ->timezone(config('app.display_timezone'))
            ->locale('fr')
            ->translatedFormat('j F Y \à H:i');

        return (new MailMessage)
            ->subject('Votre invitation au back-office Allo Vaho')
            ->greeting('Bonjour,')
            ->line('Un accès administrateur au back-office Allo Vaho vous a été attribué, avec le rôle '.$this->invitation->role->label().'.')
            ->action('Activer mon compte', $this->url)
            ->line('Ce lien est personnel et ne fonctionne qu’une fois. Il est valable jusqu’au '.$expiresAt.' (heure de Douala).')
            ->line('Si vous n’attendiez pas cette invitation, ignorez ce message.')
            ->salutation('L’équipe Allo Vaho');
    }
}
