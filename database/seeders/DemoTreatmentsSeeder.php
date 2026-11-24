<?php

namespace Database\Seeders;

use App\Actions\Billing\RegisterPayment;
use App\Actions\Billing\ScheduleInstallments;
use App\Actions\Consultations\RecordConsultation;
use App\Actions\Treatments\ManageTreatmentItems;
use App\Data\ConsultationData;
use App\Enums\AdjustmentType;
use App\Enums\PaymentMethod;
use App\Enums\Role;
use App\Enums\TreatmentStatus;
use App\Models\Patient;
use App\Models\Procedure;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;
use RuntimeException;

/**
 * Planes de tratamiento, consultas y pagos de demostración, creados con las
 * mismas acciones que usa la aplicación (así respetan todas las reglas).
 */
class DemoTreatmentsSeeder extends Seeder
{
    public function run(): void
    {
        if (app()->isProduction()) {
            throw new RuntimeException('Los datos de demostración no se cargan en producción.');
        }

        $items = app(ManageTreatmentItems::class);
        $payments = app(RegisterPayment::class);
        $consultations = app(RecordConsultation::class);
        $dentists = User::role(Role::Dentist->value)->where('is_active', true)->orderBy('id')->get();
        $reception = User::role(Role::Reception->value)->where('is_active', true)->firstOrFail();
        $admin = User::role(Role::Admin->value)->orderBy('id')->firstOrFail();
        $proc = Procedure::query()->pluck('id', 'name');
        $today = CarbonImmutable::today();

        // Pacientes sin alergias para que las consultas demo no requieran confirmación.
        $patients = Patient::query()->whereDoesntHave('allergies')->orderBy('id')->limit(12)->get();

        foreach ($patients as $i => $patient) {
            /** @var User $dentist */
            $dentist = $dentists[$i % $dentists->count()];
            $plan = $patient->treatmentPlans()->create([
                'title' => ['Rehabilitación sector posterior', 'Tratamiento integral', 'Control y prevención', 'Endodoncia y corona'][$i % 4],
                'professional_id' => $dentist->id,
                'created_by' => $dentist->id,
            ]);

            $tooth = [16, 26, 36, 46, 14, 24][$i % 6];
            $obturacion = $items->add($plan, $dentist, ['procedure_id' => $proc['Obturación con resina'], 'tooth_code' => $tooth, 'surface' => 'oclusal', 'price' => 250000]);
            $limpieza = $items->add($plan, $dentist, ['procedure_id' => $proc['Limpieza (profilaxis)'], 'price' => 200000]);
            $endo = $items->add($plan, $dentist, ['procedure_id' => $proc['Endodoncia multirradicular'], 'tooth_code' => $tooth + 1, 'price' => 1400000]);
            $corona = $items->add($plan, $dentist, ['procedure_id' => $proc['Corona de porcelana'], 'tooth_code' => $tooth + 1, 'price' => 2200000]);

            if ($i % 4 === 3) {
                continue; // queda como presupuesto sin aceptar
            }

            foreach ([$obturacion, $limpieza, $endo] as $item) {
                $items->changeStatus($item, TreatmentStatus::Accepted);
            }

            // Consulta pasada que completa la obturación y la limpieza (y actualiza el odontograma).
            $consultedAt = $today->subDays(20 + $i)->setTime(9 + $i % 8, 0);
            $consultations->handle($patient, $dentist, new ConsultationData(
                consultedAt: $consultedAt,
                reason: ['Control y limpieza', 'Dolor al masticar', 'Sensibilidad al frío', 'Revisión semestral'][$i % 4],
                subjective: 'Refiere molestia leve al masticar del lado '.($i % 2 ? 'derecho' : 'izquierdo').'.',
                objective: "Caries oclusal en pieza {$tooth}. Encías con inflamación leve.",
                assessment: 'Caries dentinaria. Gingivitis leve.',
                plan: 'Obturación con resina y profilaxis. Control en 6 meses.',
                materials: 'Resina A2, ácido grabador, adhesivo. Lidocaína 2% (1 carpule).',
                observations: null,
                nextVisit: 'Control en 6 meses',
                nextVisitOn: $consultedAt->addMonths(6)->startOfDay(),
                anesthesiaUsed: true,
                allergiesAcknowledged: false,
                appointmentId: null,
                procedures: [
                    ['procedure_id' => 0, 'treatment_item_id' => $obturacion->id, 'tooth_code' => null, 'surface' => null, 'notes' => null],
                    ['procedure_id' => 0, 'treatment_item_id' => $limpieza->id, 'tooth_code' => null, 'surface' => null, 'notes' => null],
                ],
            ));

            if ($i % 2 === 0) {
                $items->changeStatus($endo, TreatmentStatus::InProgress);
            }

            if ($i === 0) {
                $items->changeStatus($corona, TreatmentStatus::Cancelled, 'El paciente prefirió postergarlo.');
                $items->adjust($obturacion->refresh(), $admin, AdjustmentType::Price, '200000', 'Descuento acordado por pago al contado.');
            }

            // Pagos: un adelanto y, en algunos, un segundo pago.
            $payments->handle($patient, $reception, 300000, PaymentMethod::Cash, $consultedAt->setTime(12, 0), $plan);

            if ($i % 3 === 1) {
                $payments->handle($patient, $reception, 500000, PaymentMethod::Transfer, $consultedAt->addDays(7)->setTime(10, 30), $plan, 'TRF-'.(1000 + $i));
            }

            if ($i === 1) {
                $wrong = $payments->handle($patient, $reception, 50000, PaymentMethod::Qr, $consultedAt->addDays(8)->setTime(9, 0), $plan, 'QR-77');
                $payments->void($wrong, $admin, 'Se cargó dos veces el mismo pago.');
            }

            if ($i % 4 === 2) {
                app(ScheduleInstallments::class)->handle($plan->refresh(), 3, $today->addDays(5));
            }
        }
    }
}
