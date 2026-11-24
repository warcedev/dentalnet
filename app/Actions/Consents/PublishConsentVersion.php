<?php

namespace App\Actions\Consents;

use App\Models\Consent;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Crea una plantilla nueva o una nueva versión de una existente. La versión
 * anterior queda inactiva pero se conserva, porque hay firmas que la referencian.
 */
class PublishConsentVersion
{
    public function handle(User $actor, string $title, string $body, ?Consent $previous = null): Consent
    {
        return DB::transaction(function () use ($actor, $title, $body, $previous) {
            $code = $previous->code ?? $this->uniqueCode($title);
            $version = (int) Consent::query()->where('code', $code)->max('version') + 1;

            Consent::query()->where('code', $code)->where('is_active', true)->get()
                ->each(fn (Consent $c) => $c->update(['is_active' => false]));

            return Consent::create([
                'code' => $code,
                'version' => $version,
                'title' => $title,
                'body' => $body,
                'is_active' => true,
                'created_by' => $actor->id,
            ]);
        });
    }

    public function deactivate(Consent $consent): void
    {
        $consent->update(['is_active' => false]);
    }

    private function uniqueCode(string $title): string
    {
        $base = Str::slug($title) ?: 'consentimiento';
        $code = $base;
        $i = 2;

        while (Consent::query()->where('code', $code)->exists()) {
            $code = $base.'-'.$i++;
        }

        return $code;
    }
}
