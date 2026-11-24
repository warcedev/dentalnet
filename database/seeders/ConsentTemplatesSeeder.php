<?php

namespace Database\Seeders;

use App\Actions\Consents\PublishConsentVersion;
use App\Models\Consent;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * Plantillas de EJEMPLO. Deben ser revisadas por un asesor legal y por el
 * responsable del consultorio antes de usarse con pacientes reales.
 */
class ConsentTemplatesSeeder extends Seeder
{
    private const DISCLAIMER = "[PLANTILLA DE EJEMPLO — validar con asesoría legal antes de usar]\n\n";

    public function run(User $author): void
    {
        if (Consent::query()->exists()) {
            return;
        }

        $publish = app(PublishConsentVersion::class);

        foreach ($this->templates() as $title => $body) {
            $publish->handle($author, $title, self::DISCLAIMER.$body);
        }
    }

    /** @return array<string, string> */
    private function templates(): array
    {
        $closing = "\n\nDeclaro que se me explicó en lenguaje claro el procedimiento, sus beneficios, riesgos y alternativas, que pude hacer preguntas y que fueron respondidas. Sé que puedo revocar este consentimiento antes del procedimiento.\n\nPaciente: {paciente} — {documento}\nProfesional: {profesional}\nFecha: {fecha}";

        return [
            'Consentimiento general de atención odontológica' => "Yo, {paciente}, autorizo a {consultorio} y a su equipo profesional a realizar el examen clínico, las radiografías y los tratamientos odontológicos de rutina que sean necesarios.\n\nEntiendo que debo informar mis antecedentes médicos, alergias y medicación, y que la omisión de esa información puede afectar mi salud.".$closing,
            'Consentimiento para anestesia local' => "Yo, {paciente}, autorizo la aplicación de anestesia local para el procedimiento indicado.\n\nRiesgos posibles: dolor o hematoma en el sitio de la punción, adormecimiento prolongado, reacción alérgica, aumento transitorio de la frecuencia cardíaca, lesión de la mucosa por mordedura mientras dura el efecto.\n\nDeclaro haber informado si tengo alergias, enfermedades cardíacas, si estoy embarazada o tomo anticoagulantes.".$closing,
            'Consentimiento para extracción dental' => "Yo, {paciente}, autorizo la extracción de la/s pieza/s dentaria/s indicada/s por {profesional}.\n\nRiesgos posibles: dolor, inflamación, sangrado, infección, alveolitis, lesión de piezas vecinas, comunicación con el seno maxilar, adormecimiento temporal o permanente del labio o la lengua, fractura de la raíz o del hueso.\n\nMe comprometo a seguir las indicaciones postoperatorias.".$closing,
            'Consentimiento para tratamiento de conducto (endodoncia)' => "Yo, {paciente}, autorizo la realización de un tratamiento de conducto.\n\nEntiendo que el tratamiento tiene una alta tasa de éxito pero no está garantizado; que pueden presentarse dolor o inflamación posoperatoria, fractura de instrumentos dentro del conducto, perforaciones o necesidad de retratamiento o cirugía; y que la pieza tratada puede requerir una restauración o corona posterior.".$closing,
        ];
    }
}
