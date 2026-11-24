<?php

namespace Database\Seeders;

use App\Actions\Clinical\RecordMedicalHistory;
use App\Actions\Consents\SignConsent;
use App\Enums\AllergyType;
use App\Enums\ConditionType;
use App\Enums\Role;
use App\Enums\Severity;
use App\Enums\SignerRelationship;
use App\Models\Consent;
use App\Models\Patient;
use App\Models\User;
use Illuminate\Database\Seeder;
use RuntimeException;

/**
 * Pacientes de demostración con fichas clínicas variadas.
 */
class DemoPatientsSeeder extends Seeder
{
    public function run(): void
    {
        if (app()->isProduction()) {
            throw new RuntimeException('Los datos de demostración no se cargan en producción.');
        }

        $dentist = User::role(Role::Dentist->value)->orderBy('id')->firstOrFail();
        $reception = User::role(Role::Reception->value)->where('is_active', true)->firstOrFail();

        (new ConsentTemplatesSeeder)->run($dentist);

        $patients = Patient::factory()->count(26)->create(['created_by' => $reception->id])
            ->concat(Patient::factory()->minor()->count(4)->create(['created_by' => $reception->id]));

        $history = app(RecordMedicalHistory::class);

        $allergies = [
            [AllergyType::Drug, 'Penicilina', Severity::Severe, 'Edema de glotis en 2019'],
            [AllergyType::Anesthetic, 'Lidocaína', Severity::Moderate, 'Taquicardia y urticaria'],
            [AllergyType::Latex, 'Látex', Severity::Moderate, 'Dermatitis de contacto'],
            [AllergyType::Drug, 'Ibuprofeno', Severity::Mild, 'Erupción cutánea'],
            [AllergyType::Drug, 'Amoxicilina', Severity::Severe, 'Anafilaxia'],
        ];

        $conditions = [
            [ConditionType::Hypertension, 'Controlada con losartán 50 mg'],
            [ConditionType::Diabetes, 'Tipo 2, metformina'],
            [ConditionType::Anticoagulants, 'Warfarina — pedir INR antes de cirugía'],
            [ConditionType::HeartDisease, 'Portador de válvula protésica'],
            [ConditionType::Asthma, null],
            [ConditionType::Pregnancy, '24 semanas'],
        ];

        foreach ($patients as $i => $patient) {
            /** @var Patient $patient */
            if ($i % 3 !== 2) {
                $history->handle($patient, $dentist, [
                    'medical_background' => fake()->randomElement(['Sin antecedentes de relevancia.', 'Gastritis crónica.', 'Hipotiroidismo en tratamiento.', 'Migrañas ocasionales.']),
                    'current_medication' => fake()->randomElement([null, 'Levotiroxina 50 mcg', 'Omeprazol 20 mg', 'Anticonceptivos orales']),
                    'surgical_history' => fake()->randomElement([null, 'Apendicectomía (2015)', 'Cesárea (2018)']),
                    'habits' => fake()->randomElement(['No fuma.', 'Fumador (10 cigarrillos/día).', 'Bruxismo nocturno.', 'Toma tereré a diario.']),
                ]);
            }

            if ($i % 4 === 0) {
                [$type, $allergen, $severity, $reaction] = $allergies[$i / 4 % count($allergies)];
                $patient->allergies()->create(['type' => $type, 'allergen' => $allergen, 'severity' => $severity, 'reaction' => $reaction, 'recorded_by' => $dentist->id]);
            }

            if ($i % 5 === 1) {
                [$condition, $notes] = $conditions[intdiv($i, 5) % count($conditions)];

                if ($condition === ConditionType::Pregnancy && $patient->sex->value !== 'f') {
                    $condition = ConditionType::Asthma;
                }

                $patient->conditions()->create(['condition' => $condition, 'notes' => $notes, 'recorded_by' => $dentist->id]);
            }
        }

        // Una corrección de antecedentes para mostrar el historial de versiones.
        $first = $patients->first();
        $history->handle($first, $dentist, [
            'medical_background' => 'Hipotiroidismo en tratamiento. Refiere episodios de hipertensión.',
            'current_medication' => 'Levotiroxina 75 mcg',
            'change_reason' => 'Actualización en control: ajuste de dosis indicado por su médico.',
        ]);

        // Algunos consentimientos firmados.
        $general = Consent::query()->current()->where('title', 'like', '%general%')->firstOrFail();
        $sign = app(SignConsent::class);

        foreach ($patients->take(5) as $patient) {
            /** @var Patient $patient */
            $sign->handle($patient, $general, $dentist, [
                'signer_name' => $patient->is_minor ? (string) $patient->guardian_name : $patient->full_name,
                'signer_document' => $patient->is_minor ? (string) $patient->guardian_document : $patient->document_number,
                'signer_relationship' => $patient->is_minor ? SignerRelationship::Parent : SignerRelationship::Patient,
                'signature' => DemoSignature::dataUrl(),
            ]);
        }
    }
}
