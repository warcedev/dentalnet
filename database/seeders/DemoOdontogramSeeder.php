<?php

namespace Database\Seeders;

use App\Actions\Odontogram\CorrectToothEvent;
use App\Actions\Odontogram\RecordToothEvent;
use App\Data\ToothEventData;
use App\Enums\Role;
use App\Enums\ToothCondition;
use App\Enums\ToothEventStatus;
use App\Enums\ToothSurface;
use App\Models\Patient;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;
use RuntimeException;

/**
 * Historias de odontograma de demostración, repartidas en los últimos dos años.
 */
class DemoOdontogramSeeder extends Seeder
{
    public function run(): void
    {
        if (app()->isProduction()) {
            throw new RuntimeException('Los datos de demostración no se cargan en producción.');
        }

        $dentists = User::role(Role::Dentist->value)->orderBy('id')->get();
        $record = app(RecordToothEvent::class);
        $today = CarbonImmutable::today();

        $patients = Patient::query()->orderBy('id')->get();

        foreach ($patients as $i => $patient) {
            /** @var User $dentist */
            $dentist = $dentists[$i % $dentists->count()];
            $minor = $patient->is_minor;
            $start = $today->subMonths(24 - ($i % 6));

            // Limpiezas periódicas (algunas atrasadas a propósito).
            $cleanings = $i % 3 === 0 ? [$start, $start->addMonths(7)] : [$start->addMonths(2), $start->addMonths(8), $start->addMonths(15)];

            foreach ($cleanings as $date) {
                if ($date->lte($today)) {
                    $record->handle($patient, $dentist, $this->data(null, null, ToothCondition::Cleaning, ToothEventStatus::Treated, $date, $date));
                }
            }

            $teeth = $minor ? [54, 64, 74, 84, 55, 75] : [16, 26, 36, 46, 15, 47, 11, 24];
            $a = $teeth[$i % count($teeth)];
            $b = $teeth[($i + 3) % count($teeth)];

            // Caries detectada y luego obturada (muestra la línea de tiempo).
            $record->handle($patient, $dentist, $this->data($a, ToothSurface::Occlusal, ToothCondition::Restoration, ToothEventStatus::Treated, $start->addMonths(1), $start->addMonths(3)));
            // Caries pendiente.
            $record->handle($patient, $dentist, $this->data($b, ToothSurface::Mesial, ToothCondition::Caries, ToothEventStatus::Diagnosed, $start->addMonths(10), null));

            if (! $minor && $i % 2 === 0) {
                $record->handle($patient, $dentist, $this->data(36, null, ToothCondition::RootCanal, ToothEventStatus::Treated, $start->addMonths(4), $start->addMonths(5)));
                $record->handle($patient, $dentist, $this->data(36, null, ToothCondition::Crown, ToothEventStatus::Treated, $start->addMonths(5), $start->addMonths(6)));
            }

            if (! $minor && $i % 4 === 1) {
                $record->handle($patient, $dentist, $this->data(46, null, ToothCondition::Extraction, ToothEventStatus::Treated, $start->addMonths(6), $start->addMonths(7)));
                $record->handle($patient, $dentist, $this->data(46, null, ToothCondition::Implant, ToothEventStatus::Treated, $start->addMonths(12), $start->addMonths(14)));
            }

            if (! $minor && $i % 5 === 2) {
                $record->handle($patient, $dentist, $this->data(18, null, ToothCondition::Missing, ToothEventStatus::Treated, $start, $start));
                $record->handle($patient, $dentist, $this->data(28, null, ToothCondition::Extraction, ToothEventStatus::Diagnosed, $start->addMonths(11), null));
            }
        }

        // Una corrección y una anulación en el primer paciente.
        $first = $patients->first();
        $dentist = $dentists->first();

        if ($first !== null && $dentist !== null) {
            $wrong = $record->handle($first, $dentist, $this->data(21, ToothSurface::Vestibular, ToothCondition::Caries, ToothEventStatus::Diagnosed, $today->subMonths(2), null));
            app(CorrectToothEvent::class)->correct(
                $wrong,
                $dentist,
                $this->data(22, ToothSurface::Vestibular, ToothCondition::Caries, ToothEventStatus::Diagnosed, $today->subMonths(2), null),
                'Se había cargado en el diente equivocado (era el 22).',
            );

            $duplicate = $record->handle($first, $dentist, $this->data(13, ToothSurface::Distal, ToothCondition::Sealant, ToothEventStatus::Treated, $today->subMonth(), $today->subMonth()));
            app(CorrectToothEvent::class)->void($duplicate, $dentist, 'Registro duplicado por error.');
        }
    }

    private function data(?int $tooth, ?ToothSurface $surface, ToothCondition $condition, ToothEventStatus $status, CarbonImmutable $detected, ?CarbonImmutable $treated): ToothEventData
    {
        $today = CarbonImmutable::today();
        $detected = $detected->min($today);
        $treated = $treated?->min($today);

        return new ToothEventData($tooth, $surface, $condition, $status, $detected, $treated, null);
    }
}
