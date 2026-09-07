<?php

namespace App\Notifications;

use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Notifications\Messages\MailMessage;

class ResetPasswordNotification extends ResetPassword
{
    public function toMail($notifiable): MailMessage
    {
        $url = route('password.reset', [
            'token' => $this->token,
            'email' => $notifiable->getEmailForPasswordReset(),
        ]);
        $minutes = (int) config('auth.passwords.'.config('auth.defaults.passwords').'.expire', 60);

        return (new MailMessage)
            ->subject('Recuperación de contraseña | ASONACOP')
            ->greeting('Hola, '.$notifiable->name)
            ->line('Recibimos una solicitud para restablecer la contraseña de su cuenta.')
            ->action('Restablecer contraseña', $url)
            ->line("Este enlace vencerá en {$minutes} minutos.")
            ->line('Si usted no realizó esta solicitud, puede ignorar este mensaje.');
    }
}
