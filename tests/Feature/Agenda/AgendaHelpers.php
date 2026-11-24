<?php

use App\Models\AppointmentType;
use App\Models\Patient;
use App\Models\Room;
use App\Models\User;
use Carbon\CarbonImmutable;

/** Martes 1/12/2026 a las 8:00 (día hábil). */
function agendaNow(): CarbonImmutable
{
    return CarbonImmutable::parse('2026-12-01 08:00:00');
}

function agendaSetup(object $test): void
{
    $test->travelTo(agendaNow());
    $test->dentist = User::factory()->dentist()->create(['name' => 'Dr. Uno']);
    $test->otherDentist = User::factory()->dentist()->create(['name' => 'Dra. Dos']);
    $test->reception = User::factory()->reception()->create();
    $test->room = Room::query()->orderBy('id')->firstOrFail();
    $test->otherRoom = Room::query()->orderBy('id')->skip(1)->firstOrFail();
    $test->type = AppointmentType::query()->where('name', 'Consulta / control')->firstOrFail();
    $test->patient = Patient::factory()->create(['accepts_communications' => true, 'email' => 'ana@ejemplo.com']);
}

/** @return array<string, mixed> */
function bookingPayload(object $test, array $overrides = []): array
{
    return array_merge([
        'patient_id' => $test->patient->id,
        'professional_id' => $test->dentist->id,
        'room_id' => $test->room->id,
        'appointment_type_id' => $test->type->id,
        'date' => '2026-12-02',
        'start_time' => '10:00',
        'duration_minutes' => 30,
        'notes' => '',
    ], $overrides);
}
