<?php

namespace App\Domain\Admin\Notifications;

use App\Domain\Admin\Models\AdminInvitation;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Tells the administrator who issued an invitation that its recipient asks
 * for a new one. Nothing is created automatically.
 */
class AdminInvitationRenewalRequested extends Notification
{
    public function __construct(public readonly AdminInvitation $invitation) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Demande d’une nouvelle invitation au back-office')
            ->greeting('Bonjour,')
            ->line('L’invitation envoyée à '.$this->invitation->email.' (rôle '.$this->invitation->role->label().') a expiré avant d’être utilisée.')
            ->line('Son destinataire demande une nouvelle invitation. Aucune invitation n’a été créée automatiquement.')
            ->salutation('L’équipe Allo Vaho');
    }
}
