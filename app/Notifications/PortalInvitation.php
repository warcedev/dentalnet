<?php

namespace App\Notifications;

use App\Models\PatientAccount;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Invitación (o restablecimiento) para crear la contraseña del portal.
 */
class PortalInvitation extends Notification
{
    public function __construct(#[\SensitiveParameter] public readonly string $token) {}

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(PatientAccount $notifiable): MailMessage
    {
        $url = route('portal.password.edit', ['token' => $this->token, 'email' => $notifiable->email]);
        $clinic = (string) config('app.name');

        return (new MailMessage)
            ->subject("Acceso a su portal de paciente en {$clinic}")
            ->greeting('Hola, '.$notifiable->patient->first_name.':')
            ->line("Puede consultar su odontograma, sus próximas citas, su saldo, sus recetas y sus recibos en el portal de {$clinic}.")
            ->action('Crear mi contraseña', $url)
            ->line('El enlace vence en 72 horas. Si no solicitó este acceso, ignore este mensaje.')
            ->salutation('Saludos, '.$clinic);
    }
}
