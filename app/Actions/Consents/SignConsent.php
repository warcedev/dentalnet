<?php

namespace App\Actions\Consents;

use App\Enums\SignerRelationship;
use App\Models\Consent;
use App\Models\ConsentSignature;
use App\Models\Patient;
use App\Models\User;
use App\Services\Clinical\SignatureImage;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * Registra un consentimiento firmado: guarda la firma en almacenamiento
 * privado, una copia literal del texto aceptado y los hashes de ambos.
 */
class SignConsent
{
    public const DISK = 'local';

    public function __construct(
        private readonly SignatureImage $signatureImage,
        private readonly ConsentRenderer $renderer,
    ) {}

    /**
     * @param  array{signer_name: string, signer_document: string, signer_relationship: SignerRelationship, signature: string}  $data
     */
    public function handle(Patient $patient, Consent $consent, User $witness, array $data, ?string $ip = null, ?string $userAgent = null): ConsentSignature
    {
        if (! $consent->is_active) {
            throw ValidationException::withMessages(['consent_id' => 'Esta versión del consentimiento ya no está vigente. Recargue la página.']);
        }

        // Regla: un menor no firma por sí mismo.
        if ($patient->is_minor && $data['signer_relationship'] === SignerRelationship::Patient) {
            throw ValidationException::withMessages([
                'signer_relationship' => 'El paciente es menor de edad: debe firmar el padre, la madre o el tutor legal.',
            ]);
        }

        $png = $this->signatureImage->decode($data['signature']);
        $body = $this->renderer->render($consent, $patient, $witness);
        $path = sprintf('consentimientos/%d/%s.png', $patient->id, Str::uuid());

        Storage::disk(self::DISK)->put($path, $png);

        try {
            return DB::transaction(fn () => ConsentSignature::create([
                'patient_id' => $patient->id,
                'consent_id' => $consent->id,
                'consent_title' => $consent->title,
                'consent_version' => $consent->version,
                'body_snapshot' => $body,
                'body_hash' => hash('sha256', $body),
                'signer_name' => trim($data['signer_name']),
                'signer_document' => trim($data['signer_document']),
                'signer_relationship' => $data['signer_relationship'],
                'signature_path' => $path,
                'signature_hash' => hash('sha256', $png),
                'witnessed_by' => $witness->id,
                'ip_address' => $ip,
                'user_agent' => $userAgent ? Str::limit($userAgent, 250, '') : null,
                'signed_at' => now(),
            ]));
        } catch (Throwable $e) {
            Storage::disk(self::DISK)->delete($path);

            throw $e;
        }
    }
}
