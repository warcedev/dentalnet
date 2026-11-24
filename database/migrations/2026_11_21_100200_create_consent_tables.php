<?php

use App\Support\Database\ImmutableTable;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Plantillas de consentimiento. "Editar" crea una versión nueva con el
        // mismo código; las versiones anteriores quedan para lo ya firmado.
        Schema::create('consents', function (Blueprint $table) {
            $table->id();
            $table->string('code', 60);
            $table->unsignedInteger('version');
            $table->string('title', 160);
            $table->longText('body');
            $table->boolean('is_active')->default(true);
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();

            $table->unique(['code', 'version']);
            $table->index(['code', 'is_active']);
        });

        Schema::create('consent_signatures', function (Blueprint $table) {
            $table->id();
            $table->foreignId('patient_id')->constrained()->restrictOnDelete();
            $table->foreignId('consent_id')->constrained()->restrictOnDelete();
            // Copia literal del texto aceptado y su hash: prueba aunque cambie la plantilla.
            $table->string('consent_title', 160);
            $table->unsignedInteger('consent_version');
            $table->longText('body_snapshot');
            $table->char('body_hash', 64);
            $table->string('signer_name', 120);
            $table->string('signer_document', 30);
            $table->string('signer_relationship', 20);
            $table->string('signature_path', 255);
            $table->char('signature_hash', 64);
            $table->foreignId('witnessed_by')->constrained('users')->restrictOnDelete();
            $table->string('ip_address', 45)->nullable();
            $table->string('user_agent', 255)->nullable();
            $table->timestamp('signed_at');

            $table->index(['patient_id', 'signed_at']);
        });

        ImmutableTable::protect('consent_signatures');
    }

    public function down(): void
    {
        ImmutableTable::release('consent_signatures');
        Schema::dropIfExists('consent_signatures');
        Schema::dropIfExists('consents');
    }
};
