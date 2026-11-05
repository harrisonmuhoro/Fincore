<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ActivateAccountNotification extends Notification
{
    use Queueable;

    public function __construct(private readonly string $token) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $frontend = rtrim((string) config('app.frontend_url', config('app.url')), '/');
        $activationUrl = $frontend.'/activate-account?'.http_build_query(['token' => $this->token, 'email' => $notifiable->email]);

        return (new MailMessage)
            ->subject('Activate your FinCore County account')
            ->greeting('Account invitation')
            ->line('An authorized administrator invited you to access FinCore County.')
            ->action('Activate account', $activationUrl)
            ->line('This link expires according to the configured password reset token policy. Contact your administrator if it expires.');
    }
}
