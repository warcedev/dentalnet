<?php

namespace Database\Seeders;

use App\Actions\Documents\IssuePrescription;
use App\Actions\Documents\StoreDocument;
use App\Enums\DocumentCategory;
use App\Enums\LabOrderStatus;
use App\Enums\LabWorkType;
use App\Enums\PrescriptionType;
use App\Enums\Role;
use App\Models\Patient;
use App\Models\PatientAccount;
use App\Models\Tooth;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Documentos, órdenes de laboratorio, recetas, periodontogramas y una cuenta
 * del portal de demostración (contraseña del portal: "portal12345").
 */
class DemoDocumentsSeeder extends Seeder
{
    public function run(): void
    {
        if (app()->isProduction()) {
            throw new RuntimeException('Los datos de demostración no se cargan en producción.');
        }

        $dentist = User::role(Role::Dentist->value)->where('is_active', true)->orderBy('id')->firstOrFail();
        $today = CarbonImmutable::today();
        $patients = Patient::query()->whereDoesntHave('allergies')->orderBy('id')->limit(6)->get();

        foreach ($patients as $i => $patient) {
            $this->documents($patient, $dentist, $today, $i);
            $this->labOrder($patient, $dentist, $today, $i);
            app(IssuePrescription::class)->handle($patient, $dentist, PrescriptionType::Prescription, [
                ['drug' => 'Ibuprofeno 400 mg', 'dose' => '1 comprimido', 'frequency' => 'cada 8 horas', 'duration' => '3 días'],
                ['drug' => 'Clorhexidina 0,12 % colutorio', 'dose' => '15 ml', 'frequency' => 'cada 12 horas', 'duration' => '7 días'],
            ], 'Tomar el ibuprofeno después de las comidas.', false);
        }

        if (($first = $patients->first()) !== null) {
            $this->periodontal($first, $dentist, $today);

            PatientAccount::query()->create([
                'patient_id' => $first->id,
                'email' => 'paciente@demo.test',
                'password' => 'portal12345',
                'is_active' => true,
                'invited_at' => now(),
            ]);
        }
    }

    private function documents(Patient $patient, User $dentist, CarbonImmutable $today, int $i): void
    {
        $tooth = [16, 26, 36, 46, 14, 24][$i % 6];
        $path = tempnam(sys_get_temp_dir(), 'demo').'.png';
        $image = imagecreatetruecolor(480, 320);

        if ($image === false) {
            return;
        }

        // Imagen gris tipo radiografía con un "diente" claro: solo para la demo.
        imagefill($image, 0, 0, (int) imagecolorallocate($image, 30, 30, 30));
        imagefilledellipse($image, 240, 140, 140, 180, (int) imagecolorallocate($image, 200, 200, 200));
        imagefilledrectangle($image, 205, 200, 230, 310, (int) imagecolorallocate($image, 170, 170, 170));
        imagefilledrectangle($image, 250, 200, 275, 310, (int) imagecolorallocate($image, 170, 170, 170));
        imagepng($image, $path);
        imagedestroy($image);

        app(StoreDocument::class)->handle(
            $patient,
            $dentist,
            new UploadedFile($path, "rx-{$tooth}.png", 'image/png', null, true),
            DocumentCategory::Radiograph,
            "Radiografía periapical pieza {$tooth}",
            'Control previo al tratamiento.',
            $tooth,
            $today->subDays(25 + $i),
        );

        @unlink($path);
    }

    private function labOrder(Patient $patient, User $dentist, CarbonImmutable $today, int $i): void
    {
        $status = [LabOrderStatus::Sent, LabOrderStatus::Received, LabOrderStatus::Delivered][$i % 3];
        $sent = $today->subDays(12 + $i);

        $patient->labOrders()->create([
            'laboratory' => ['Laboratorio Dental Asunción', 'ProDent Lab'][$i % 2],
            'work_type' => [LabWorkType::Crown, LabWorkType::Bridge][$i % 2],
            'teeth' => (string) ([17, 27, 37, 47, 15, 25][$i % 6]),
            'shade' => 'A2',
            'instructions' => 'Porcelana sobre metal. Contacto proximal firme.',
            'sent_on' => $sent,
            // La primera queda vencida para mostrar la alerta.
            'due_on' => $i === 0 ? $today->subDays(2) : $sent->addDays(14),
            'received_on' => $status !== LabOrderStatus::Sent ? $sent->addDays(9) : null,
            'delivered_on' => $status === LabOrderStatus::Delivered ? $sent->addDays(11) : null,
            'status' => $status,
            'created_by' => $dentist->id,
        ]);
    }

    private function periodontal(Patient $patient, User $dentist, CarbonImmutable $today): void
    {
        $codes = Tooth::query()->where('dentition', 'permanente')->whereNotIn('code', [18, 28, 38, 48])->pluck('code');

        // Tres exámenes con mejoría progresiva tras el tratamiento periodontal.
        foreach ([[240, 0], [150, 1], [60, 2]] as [$daysAgo, $step]) {
            DB::transaction(function () use ($patient, $dentist, $today, $codes, $daysAgo, $step) {
                $record = $patient->periodontalRecords()->create([
                    'professional_id' => $dentist->id,
                    'recorded_on' => $today->subDays($daysAgo),
                    'notes' => ['Examen inicial. Gingivitis generalizada.', 'Reevaluación tras raspado y alisado.', 'Mantenimiento.'][$step],
                ]);

                foreach ($codes as $n => $code) {
                    $seed = ($n * 7 + $step * 3) % 10;
                    $deep = in_array($code, [16, 26, 36, 46], true);
                    $depth = fn (int $s) => max(1, ($deep ? 5 : 3) + (($seed + $s) % 3) - 1 - $step);
                    $record->measurements()->create([
                        'tooth_code' => $code,
                        'is_missing' => false,
                        'probing_depth' => array_map($depth, range(0, 5)),
                        'recession' => array_map(fn (int $s) => ($seed + $s) % 5 === 0 ? 1 : 0, range(0, 5)),
                        'bleeding' => array_map(fn (int $s) => ($seed + $s * 3) % 10 < (6 - $step * 2), range(0, 5)),
                        'plaque' => array_map(fn (int $s) => ($seed + $s * 5) % 10 < (5 - $step), range(0, 5)),
                        'mobility' => $deep && $step === 0 ? 1 : 0,
                    ]);
                }
            });
        }
    }
}
