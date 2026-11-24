<?php

namespace App\Services\Reminders;

use App\Enums\ReminderType;
use App\Models\Appointment;
use App\Models\Reminder;
use App\Services\Reminders\Channels\ReminderMessage;

/**
 * Redacta cada recordatorio. No incluye datos clínicos: un correo puede
 * leerlo otra persona.
 */
class ReminderComposer
{
    public function compose(Reminder $reminder): ReminderMessage
    {
        $clinic = (string) config('app.name');
        $patient = $reminder->patient;
        $greeting = 'Hola, '.$patient->first_name.':';
        $contact = $this->contactLine();

        $appointment = $reminder->remindable instanceof Appointment ? $reminder->remindable : null;
        $when = $appointment ? $appointment->starts_at->format('d/m/Y').' a las '.$appointment->starts_at->format('H:i') : '';
        $with = $appointment?->professional ? ' con '.$appointment->professional->name : '';

        return match ($reminder->type) {
            ReminderType::UpcomingAppointment => new ReminderMessage(
                "Su próxima cita en {$clinic}",
                $greeting,
                ["Le recordamos su cita del {$when}{$with}.", 'Si no puede asistir, avísenos con anticipación para ofrecer el horario a otro paciente.', $contact],
            ),
            ReminderType::Confirmation => new ReminderMessage(
                "Confirme su cita de mañana en {$clinic}",
                $greeting,
                ["Tiene una cita el {$when}{$with}.", 'Por favor, confírmenos su asistencia respondiendo a este correo o llamándonos.', $contact],
            ),
            ReminderType::Cleaning => new ReminderMessage(
                "Es momento de su limpieza dental en {$clinic}",
                $greeting,
                ['Ya pasaron unos meses desde su última limpieza. Le recomendamos agendar su control y limpieza para mantener su salud bucal.', $contact],
            ),
            ReminderType::TreatmentControl => new ReminderMessage(
                "Control pendiente en {$clinic}",
                $greeting,
                ['Le recordamos que tiene pendiente un control de su tratamiento. Comuníquese con nosotros para agendarlo.', $contact],
            ),
            ReminderType::WarrantyControl => new ReminderMessage(
                "Control de su tratamiento en {$clinic}",
                $greeting,
                ['Se acerca el fin del período de garantía de un tratamiento que realizó con nosotros. Le recomendamos agendar un control antes de esa fecha.', $contact],
            ),
            ReminderType::Birthday => new ReminderMessage(
                "¡Feliz cumpleaños de parte de {$clinic}!",
                '¡Feliz cumpleaños, '.$patient->first_name.'!',
                ['Todo el equipo le desea un muy feliz día.'],
            ),
        };
    }

    private function contactLine(): string
    {
        $parts = array_filter([(string) config('clinic.address'), (string) config('clinic.phone')]);

        return $parts === [] ? 'Ante cualquier consulta, comuníquese con el consultorio.' : 'Consultorio: '.implode(' · ', $parts).'.';
    }
}
