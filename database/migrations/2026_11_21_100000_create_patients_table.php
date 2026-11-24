<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('patients', function (Blueprint $table) {
            $table->id();
            $table->string('first_name', 80);
            $table->string('last_name', 80);
            $table->string('document_type', 20);
            $table->string('document_number', 30);
            $table->date('birth_date');
            $table->char('sex', 1);

            $table->string('phone', 30)->nullable();
            $table->string('phone_alt', 30)->nullable();
            $table->string('email', 190)->nullable();
            $table->string('address', 200)->nullable();
            $table->string('city', 80)->nullable();
            $table->string('occupation', 80)->nullable();

            $table->string('emergency_contact_name', 120)->nullable();
            $table->string('emergency_contact_phone', 30)->nullable();
            $table->string('emergency_contact_relationship', 40)->nullable();

            // Obra social o prepaga: solo como dato (sin facturación a aseguradoras).
            $table->string('insurer', 80)->nullable();
            $table->string('insurance_number', 50)->nullable();

            // Tutor legal (obligatorio para firmar consentimientos de menores).
            $table->string('guardian_name', 120)->nullable();
            $table->string('guardian_document', 30)->nullable();
            $table->string('guardian_relationship', 20)->nullable();
            $table->string('guardian_phone', 30)->nullable();

            $table->text('administrative_notes')->nullable();
            // Consentimiento para recibir recordatorios y saludos (Fase 4).
            $table->boolean('accepts_communications')->default(false);

            // Columnas de búsqueda normalizadas (minúsculas, sin acentos, solo dígitos).
            $table->string('search_name', 170);
            $table->string('search_phone', 60)->nullable();

            $table->foreignId('created_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamps();
            $table->softDeletes();

            // Documento único aunque el paciente esté archivado.
            $table->unique(['document_type', 'document_number']);
            $table->index('document_number');
            $table->index('search_name');
            $table->index('search_phone');
            $table->index('birth_date');
            $table->index('deleted_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('patients');
    }
};
