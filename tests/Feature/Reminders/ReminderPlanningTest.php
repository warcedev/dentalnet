<?php

require_once __DIR__.'/../Agenda/AgendaHelpers.php';

use App\Enums\ReminderStatus;
use App\Enums\ToothCondition;
use App\Enums\ToothEventStatus;
use App\Models\Appointment;
use App\Models\AppointmentType;
use App\Models\Patient;
use App\Models\Procedure;
use App\Models\Reminder;
use App\Models\ToothEvent;
use App\Models\TreatmentItem;
use App\Models\TreatmentPlan;
use App\Services\Reminders\ReminderPlanner;

beforeEach(fn () => agendaSetup($this));

function book(object $test, array $overrides = []): Appointment
{
    $test->actingAs($test->reception)->post(route('appointments.store'), bookingPayload($test, $overrides))->assertSessionHasNoErrors();

    return Appointment::latest('id')->firstOrFail();
}

function cleaning(Patient $patient, string $date, array $extra = []): ToothEvent
{
    return ToothEvent::create(array_merge([
        'patient_id' => $patient->id, 'tooth_code' => null, 'condition' => ToothCondition::Cleaning,
        'status' => ToothEventStatus::Treated, 'detected_on' => $date, 'treated_on' => $date, 'professional_id' => test()->dentist->id,
    ], $extra));
}

describe('recordatorios de citas', function () {
    it('crea el aviso de próxima cita y la confirmación 24 h antes', function () {
        $appointment = book($this, ['date' => '2026-12-10', 'start_time' => '15:00']);

        $reminders = Reminder::orderBy('scheduled_for')->get();
        expect($reminders)->toHaveCount(2)
            ->and($reminders[0]->type->value)->toBe('proxima_cita')
            ->and($reminders[0]->scheduled_for->format('Y-m-d H:i'))->toBe('2026-12-07 15:00')
            ->and($reminders[1]->type->value)->toBe('confirmacion_24h')
            ->and($reminders[1]->scheduled_for->format('Y-m-d H:i'))->toBe('2026-12-09 15:00')
            ->and($reminders[1]->remindable_id)->toBe($appointment->id);
    });

    it('si la cita es para mañana, solo crea la confirmación', function () {
        book($this, ['date' => '2026-12-02', 'start_time' => '15:00']);

        expect(Reminder::pluck('type')->map->value->all())->toBe(['confirmacion_24h']);
    });

    it('no crea recordatorios si el paciente no aceptó comunicaciones', function () {
        $this->patient->update(['accepts_communications' => false]);
        book($this, ['date' => '2026-12-10']);

        expect(Reminder::count())->toBe(0);
    });

    it('al reprogramar cancela los anteriores y crea nuevos', function () {
        $appointment = book($this, ['date' => '2026-12-10', 'start_time' => '15:00']);

        $this->actingAs($this->reception)->put(route('appointments.update', $appointment), array_diff_key(
            bookingPayload($this, ['date' => '2026-12-11', 'start_time' => '09:00']), ['patient_id' => 1],
        ))->assertSessionHasNoErrors();

        expect(Reminder::where('status', ReminderStatus::Cancelled)->count())->toBe(2)
            ->and(Reminder::where('status', ReminderStatus::Pending)->pluck('scheduled_for')->map->format('Y-m-d H:i')->sort()->values()->all())
            ->toBe(['2026-12-08 09:00', '2026-12-10 09:00']);
    });

    it('al cancelar la cita cancela sus recordatorios pendientes', function () {
        $appointment = book($this, ['date' => '2026-12-10']);

        $this->actingAs($this->reception)->post(route('appointments.status', $appointment), ['status' => 'cancelada', 'reason' => 'Viaje']);

        expect(Reminder::where('status', ReminderStatus::Pending)->count())->toBe(0);
    });

    it('nunca duplica recordatorios', function () {
        $appointment = book($this, ['date' => '2026-12-10']);
        app(ReminderPlanner::class)->syncForAppointment($appointment);
        app(ReminderPlanner::class)->syncForAppointment($appointment->fresh());

        expect(Reminder::count())->toBe(2);
    });
});

describe('regla 5: limpieza desde la última registrada', function () {
    it('recuerda la limpieza una semana antes de cumplirse los 6 meses', function () {
        cleaning($this->patient, '2026-06-05'); // próxima: 05/12/2026 → aviso desde 28/11
        cleaning($this->patient, '2025-12-01'); // más vieja: no cuenta

        expect(app(ReminderPlanner::class)->planRecurring()['cleaning'])->toBe(1);

        $reminder = Reminder::firstOrFail();
        expect($reminder->type->value)->toBe('limpieza')
            ->and($reminder->idempotency_key)->toBe("limpieza:{$this->patient->id}:2026-12-05");
    });

    it('no recuerda antes de tiempo', function () {
        cleaning($this->patient, '2026-08-01');

        expect(app(ReminderPlanner::class)->planRecurring()['cleaning'])->toBe(0);
    });

    it('ignora una limpieza anulada', function () {
        $wrong = cleaning($this->patient, '2026-11-20');
        cleaning($this->patient, '2026-11-20', ['corrects_event_id' => $wrong->id, 'is_void' => true]);
        cleaning($this->patient, '2026-05-20');

        expect(app(ReminderPlanner::class)->planRecurring()['cleaning'])->toBe(1);
    });

    it('no recuerda si ya tiene un turno de limpieza agendado', function () {
        cleaning($this->patient, '2026-06-05');
        book($this, ['appointment_type_id' => AppointmentType::where('is_cleaning', true)->value('id'), 'date' => '2026-12-04', 'duration_minutes' => 45]);

        expect(app(ReminderPlanner::class)->planRecurring()['cleaning'])->toBe(0);
    });

    it('ejecutarlo varias veces no duplica', function () {
        cleaning($this->patient, '2026-06-05');

        app(ReminderPlanner::class)->planRecurring();
        app(ReminderPlanner::class)->planRecurring();

        expect(Reminder::where('type', 'limpieza')->count())->toBe(1);
    });
});

describe('controles y cumpleaños', function () {
    it('recuerda el control de un registro en seguimiento', function () {
        ToothEvent::create([
            'patient_id' => $this->patient->id, 'tooth_code' => 36, 'condition' => ToothCondition::RootCanal,
            'status' => ToothEventStatus::FollowUp, 'detected_on' => '2026-10-20', 'professional_id' => $this->dentist->id,
        ]);

        expect(app(ReminderPlanner::class)->planRecurring()['control'])->toBe(1);
    });

    it('saluda en el cumpleaños solo a quien aceptó comunicaciones', function () {
        $this->patient->update(['birth_date' => '1990-12-01']);
        Patient::factory()->create(['birth_date' => '1985-12-01', 'accepts_communications' => false]);

        expect(app(ReminderPlanner::class)->planRecurring()['birthday'])->toBe(1);
    });
});

describe('garantías', function () {
    it('recuerda el control 30 días antes de que venza la garantía', function () {
        $plan = TreatmentPlan::create(['patient_id' => $this->patient->id, 'title' => 'P', 'professional_id' => $this->dentist->id]);
        $item = TreatmentItem::create([
            'treatment_plan_id' => $plan->id, 'patient_id' => $this->patient->id,
            'procedure_id' => Procedure::where('name', 'Corona de porcelana')->value('id'),
            'price' => 2200000, 'warranty_months' => 12, 'status' => 'completado', 'completed_on' => '2025-12-20',
        ]);

        expect(app(ReminderPlanner::class)->planRecurring()['warranty'])->toBe(1)
            ->and(Reminder::where('type', 'control_garantia')->value('idempotency_key'))->toBe("garantia:{$item->id}:2026-12-20");

        // Una garantía vencida ya no genera recordatorios.
        $this->travelTo('2027-01-05 08:00');
        Reminder::query()->delete();
        expect(app(ReminderPlanner::class)->planRecurring()['warranty'])->toBe(0);
    });
});
