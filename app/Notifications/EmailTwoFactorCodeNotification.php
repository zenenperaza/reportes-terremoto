<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class EmailTwoFactorCodeNotification extends Notification
{
    use Queueable;

    public function __construct(public readonly string $code)
    {
    }

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Código de acceso - Respuesta ASONACOP')
            ->greeting('Hola, '.$notifiable->name)
            ->line('Se solicitó el ingreso a su cuenta en el sistema Respuesta ASONACOP.')
            ->line('Su código de verificación es:')
            ->line('**'.$this->code.'**')
            ->line('El código vence en 10 minutos y solo puede utilizarse una vez.')
            ->line('Si usted no intentó ingresar, ignore este mensaje y comuníquese con el administrador.');
    }
}
