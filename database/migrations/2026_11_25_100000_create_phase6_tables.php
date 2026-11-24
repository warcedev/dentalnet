<?php

use App\Support\Database\ImmutableTable;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Documentos clínicos (radiografías, fotos). Polimórfica: pueden asociarse a una consulta.
        Schema::create('documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('patient_id')->constrained()->restrictOnDelete();
            $table->nullableMorphs('documentable');
            $table->unsignedTinyInteger('tooth_code')->nullable();
            $table->foreign('tooth_code')->references('code')->on('teeth')->restrictOnDelete();
            $table->string('category', 20);
            $table->string('title', 150);
            $table->text('description')->nullable(); // cifrado
            $table->string('path', 255);
            $table->string('mime', 60);
            $table->unsignedInteger('size');
            $table->char('sha256', 64);
            $table->date('taken_on')->nullable();
            $table->foreignId('uploaded_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();
            // Los documentos clínicos no se borran: se archivan.
            $table->softDeletes();

            $table->index(['patient_id', 'category']);
        });

        Schema::create('lab_orders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('patient_id')->constrained()->restrictOnDelete();
            $table->foreignId('treatment_item_id')->nullable()->constrained()->restrictOnDelete();
            $table->string('laboratory', 100);
            $table->string('work_type', 30);
            $table->string('teeth', 60)->nullable();
            $table->string('shade', 20)->nullable();
            $table->text('instructions')->nullable(); // cifrado
            $table->date('sent_on');
            $table->date('due_on');
            $table->date('received_on')->nullable();
            $table->date('delivered_on')->nullable();
            $table->string('status', 20)->default('enviado');
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();

            $table->index(['status', 'due_on']);
        });

        // Recetas e indicaciones: documentos clínicos firmados, inmutables.
        Schema::create('prescriptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('patient_id')->constrained()->restrictOnDelete();
            $table->foreignId('professional_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('consultation_id')->nullable()->constrained()->restrictOnDelete();
            $table->string('type', 20);
            $table->text('medications')->nullable(); // JSON cifrado
            $table->text('instructions')->nullable(); // cifrado
            // Regla 6: constancia de que se revisaron las alergias.
            $table->timestamp('allergies_acknowledged_at')->nullable();
            $table->timestamp('issued_at');
            $table->timestamp('created_at')->useCurrent();

            $table->index(['patient_id', 'issued_at']);
        });

        ImmutableTable::protect('prescriptions');

        // Periodontograma: cada examen es un registro inmutable.
        Schema::create('periodontal_records', function (Blueprint $table) {
            $table->id();
            $table->foreignId('patient_id')->constrained()->restrictOnDelete();
            $table->foreignId('professional_id')->constrained('users')->restrictOnDelete();
            $table->date('recorded_on');
            $table->text('notes')->nullable(); // cifrado
            $table->timestamp('created_at')->useCurrent();

            $table->index(['patient_id', 'recorded_on']);
        });

        ImmutableTable::protect('periodontal_records');

        // Una fila por diente. Los 6 puntos (MV, V, DV, ML, L, DL) van en arreglos JSON.
        Schema::create('periodontal_measurements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('periodontal_record_id')->constrained()->restrictOnDelete();
            $table->unsignedTinyInteger('tooth_code');
            $table->foreign('tooth_code')->references('code')->on('teeth')->restrictOnDelete();
            $table->boolean('is_missing')->default(false);
            $table->json('probing_depth')->nullable();
            $table->json('recession')->nullable();
            $table->json('bleeding')->nullable();
            $table->json('plaque')->nullable();
            $table->unsignedTinyInteger('mobility')->nullable();

            $table->unique(['periodontal_record_id', 'tooth_code']);
        });

        ImmutableTable::protect('periodontal_measurements');

        // Cuentas del portal del paciente (separadas de los usuarios del personal).
        Schema::create('patient_accounts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('patient_id')->unique()->constrained()->restrictOnDelete();
            $table->string('email', 190)->unique();
            $table->string('password');
            $table->boolean('is_active')->default(true);
            $table->timestamp('invited_at')->nullable();
            $table->timestamp('last_login_at')->nullable();
            $table->rememberToken();
            $table->timestamps();
        });

        Schema::create('patient_password_reset_tokens', function (Blueprint $table) {
            $table->string('email')->primary();
            $table->string('token');
            $table->timestamp('created_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('patient_password_reset_tokens');
        Schema::dropIfExists('patient_accounts');
        ImmutableTable::release('periodontal_measurements');
        Schema::dropIfExists('periodontal_measurements');
        ImmutableTable::release('periodontal_records');
        Schema::dropIfExists('periodontal_records');
        ImmutableTable::release('prescriptions');
        Schema::dropIfExists('prescriptions');
        Schema::dropIfExists('lab_orders');
        Schema::dropIfExists('documents');
    }
};
