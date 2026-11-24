<?php

namespace App\Actions\Documents;

use App\Enums\DocumentCategory;
use App\Models\Consultation;
use App\Models\Document;
use App\Models\Patient;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * Guarda un documento clínico en almacenamiento privado. El nombre del
 * archivo se genera (no se usa el original, que puede tener datos del
 * paciente) y la extensión sale del tipo real detectado.
 */
class StoreDocument
{
    public const DISK = 'local';

    private const EXTENSIONS = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
        'application/pdf' => 'pdf',
    ];

    public function handle(
        Patient $patient,
        User $uploader,
        UploadedFile $file,
        DocumentCategory $category,
        string $title,
        ?string $description = null,
        ?int $toothCode = null,
        ?CarbonImmutable $takenOn = null,
        ?Consultation $consultation = null,
    ): Document {
        $mime = (string) $file->getMimeType();
        $extension = self::EXTENSIONS[$mime] ?? null;

        if ($extension === null) {
            throw ValidationException::withMessages(['file' => 'El tipo de archivo no está permitido.']);
        }

        // Una imagen que no se puede leer como imagen es un archivo disfrazado.
        if (str_starts_with($mime, 'image/') && @getimagesize($file->getRealPath()) === false) {
            throw ValidationException::withMessages(['file' => 'La imagen está dañada o no es una imagen válida.']);
        }

        $path = $file->storeAs("documentos/{$patient->id}", Str::uuid().'.'.$extension, self::DISK);

        if ($path === false) {
            throw ValidationException::withMessages(['file' => 'No se pudo guardar el archivo. Intente de nuevo.']);
        }

        try {
            return Document::create([
                'patient_id' => $patient->id,
                'documentable_type' => $consultation?->getMorphClass(),
                'documentable_id' => $consultation?->id,
                'tooth_code' => $toothCode,
                'category' => $category,
                'title' => $title,
                'description' => $description,
                'path' => $path,
                'mime' => $mime,
                'size' => (int) $file->getSize(),
                'sha256' => (string) hash_file('sha256', $file->getRealPath()),
                'taken_on' => $takenOn,
                'uploaded_by' => $uploader->id,
            ]);
        } catch (Throwable $e) {
            Storage::disk(self::DISK)->delete($path);

            throw $e;
        }
    }
}
