<?php

namespace App\Domain\Admin\Notifications;

use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Tells an administrator that their two-factor authentication was reset and
 * must be configured again at the next sign-in.
 */
class TwoFactorResetNotification extends Notification
{
    public function __construct(public readonly string $by) {}

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
            ->subject('Votre double authentification a été réinitialisée')
            ->greeting('Bonjour,')
            ->line('La double authentification de votre compte administrateur Allo Vaho a été réinitialisée ('.$this->by.').')
            ->line('Vos sessions ont été fermées. À votre prochaine connexion, vous configurerez à nouveau Google Authenticator.')
            ->line('Si vous n’êtes pas à l’origine de cette demande, prévenez immédiatement un Super Admin.')
            ->salutation('L’équipe Allo Vaho');
    }
}
