<?php

namespace Database\Seeders;

use App\Enums\AppointmentStatus;
use App\Enums\Role;
use App\Enums\WaitingListStatus;
use App\Models\Appointment;
use App\Models\AppointmentType;
use App\Models\Patient;
use App\Models\Room;
use App\Models\ScheduleBlock;
use App\Models\User;
use App\Models\WaitingListEntry;
use App\Services\Agenda\AvailabilityChecker;
use App\Services\Reminders\ReminderPlanner;
use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;
use RuntimeException;

/**
 * Agenda de demostración: dos semanas hacia atrás y dos hacia adelante.
 * Usa el mismo verificador de disponibilidad que la aplicación, así que
 * nunca genera citas superpuestas.
 */
class DemoAgendaSeeder extends Seeder
{
    public function run(): void
    {
        if (app()->isProduction()) {
            throw new RuntimeException('Los datos de demostración no se cargan en producción.');
        }

        $checker = app(AvailabilityChecker::class);
        $planner = app(ReminderPlanner::class);
        $dentists = User::role(Role::Dentist->value)->where('is_active', true)->orderBy('id')->get();
        $reception = User::role(Role::Reception->value)->where('is_active', true)->firstOrFail();
        $rooms = Room::query()->active()->orderBy('id')->get();
        $types = AppointmentType::query()->active()->get();
        $patients = Patient::query()->inRandomOrder()->get();
        $today = CarbonImmutable::today();
        $now = CarbonImmutable::now();
        $times = ['08:00', '08:45', '09:30', '10:30', '11:15', '14:00', '15:00', '16:00', '17:00', '18:00'];
        $p = 0;

        // Bloqueo de ejemplo (el primer odontólogo, la tarde del jueves próximo).
        $thursday = $today->next(CarbonImmutable::THURSDAY);
        ScheduleBlock::create([
            'professional_id' => $dentists->first()?->id,
            'starts_at' => $thursday->setTime(14, 0),
            'ends_at' => $thursday->setTime(18, 0),
            'reason' => 'Capacitación',
            'created_by' => $reception->id,
        ]);

        for ($offset = -14; $offset <= 14; $offset++) {
            $day = $today->addDays($offset);

            if (! in_array($day->dayOfWeekIso, (array) config('clinic.agenda.working_days'), true)) {
                continue;
            }

            foreach ($dentists as $d => $dentist) {
                $room = $rooms[$d % $rooms->count()];

                foreach ($times as $t => $time) {
                    if (($t + $offset + $d) % 3 === 0) {
                        continue; // deja huecos libres
                    }

                    $type = $types[($t + $d + $offset + 20) % $types->count()];
                    $start = CarbonImmutable::createFromFormat('Y-m-d H:i', $day->toDateString().' '.$time);
                    $end = $start->addMinutes($type->duration_minutes);
                    $patient = $patients[$p++ % $patients->count()];

                    if ($start === null || $checker->check($start, $end, $dentist->id, $room->id, $patient->id) !== []) {
                        continue;
                    }

                    $status = match (true) {
                        $end->lt($now) => [AppointmentStatus::Attended, AppointmentStatus::Attended, AppointmentStatus::Attended, AppointmentStatus::NoShow, AppointmentStatus::Cancelled][($t + $offset + 50) % 5],
                        $start->lte($now) => AppointmentStatus::InProgress,
                        $offset <= 1 && $t % 2 === 0 => AppointmentStatus::Confirmed,
                        default => AppointmentStatus::Scheduled,
                    };

                    $appointment = Appointment::create([
                        'patient_id' => $patient->id,
                        'professional_id' => $dentist->id,
                        'room_id' => $room->id,
                        'appointment_type_id' => $type->id,
                        'starts_at' => $start,
                        'ends_at' => $end,
                        'status' => $status,
                        'confirmed_at' => $status === AppointmentStatus::Confirmed ? $now : null,
                        'cancelled_at' => $status === AppointmentStatus::Cancelled ? $start->subDay() : null,
                        'cancellation_reason' => $status === AppointmentStatus::Cancelled ? 'El paciente avisó que no podía asistir.' : null,
                        'created_by' => $reception->id,
                    ]);

                    $planner->syncForAppointment($appointment);
                }
            }
        }

        foreach ($patients->take(4) as $i => $patient) {
            WaitingListEntry::create([
                'patient_id' => $patient->id,
                'appointment_type_id' => $types[$i % $types->count()]->id,
                'professional_id' => $i % 2 === 0 ? $dentists->first()?->id : null,
                'preference' => ['Lunes o miércoles por la mañana', 'Después de las 17:00', 'Cualquier día', 'Sábados'][$i],
                'status' => WaitingListStatus::Waiting,
                'created_by' => $reception->id,
            ]);
        }

        $planner->planRecurring();
    }
}
