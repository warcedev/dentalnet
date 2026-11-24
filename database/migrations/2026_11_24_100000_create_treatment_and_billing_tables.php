<?php

use App\Support\Database\ImmutableTable;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Catálogo de procedimientos con precio de referencia (guaraníes, entero).
        Schema::create('procedures', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100)->unique();
            $table->unsignedBigInteger('default_price')->default(0);
            $table->unsignedSmallInteger('warranty_months')->default(0);
            $table->boolean('requires_anesthesia')->default(false);
            // Condición del odontograma que registra al realizarse (opcional).
            $table->string('tooth_condition', 20)->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('treatment_plans', function (Blueprint $table) {
            $table->id();
            $table->foreignId('patient_id')->constrained()->restrictOnDelete();
            $table->string('title', 120);
            $table->foreignId('professional_id')->constrained('users')->restrictOnDelete();
            $table->text('notes')->nullable();
            $table->timestamp('closed_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamps();

            $table->index(['patient_id', 'closed_at']);
        });

        Schema::create('treatment_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('treatment_plan_id')->constrained()->restrictOnDelete();
            $table->foreignId('patient_id')->constrained()->restrictOnDelete();
            $table->foreignId('procedure_id')->constrained()->restrictOnDelete();
            $table->string('description', 200)->nullable();
            $table->unsignedTinyInteger('tooth_code')->nullable();
            $table->foreign('tooth_code')->references('code')->on('teeth')->restrictOnDelete();
            $table->string('surface', 12)->nullable();
            // Precio acordado en guaraníes (entero). Tras completar, solo cambia con ajustes.
            $table->unsignedBigInteger('price');
            $table->unsignedSmallInteger('warranty_months')->default(0);
            $table->string('status', 20)->default('propuesto');
            $table->foreignId('professional_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->unsignedBigInteger('consultation_id')->nullable();
            $table->timestamp('accepted_at')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->date('completed_on')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->string('cancellation_reason', 300)->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamps();

            $table->index(['patient_id', 'status']);
            $table->index(['procedure_id', 'completed_on']);
        });

        // Regla 3: correcciones de tratamientos completados (solo inserciones).
        Schema::create('treatment_adjustments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('treatment_item_id')->constrained()->restrictOnDelete();
            $table->string('type', 20);
            $table->string('old_value', 200)->nullable();
            $table->string('new_value', 200)->nullable();
            $table->text('reason');
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->timestamp('created_at')->useCurrent();

            $table->index('treatment_item_id');
        });

        ImmutableTable::protect('treatment_adjustments');

        // Plan de cuotas. El estado de cada cuota se calcula a partir de los pagos.
        Schema::create('installments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('treatment_plan_id')->constrained()->restrictOnDelete();
            $table->unsignedSmallInteger('number');
            $table->date('due_date');
            $table->unsignedBigInteger('amount');
            $table->timestamps();

            $table->unique(['treatment_plan_id', 'number']);
        });

        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('patient_id')->constrained()->restrictOnDelete();
            $table->foreignId('treatment_plan_id')->nullable()->constrained()->restrictOnDelete();
            $table->unsignedBigInteger('receipt_number')->unique();
            $table->unsignedBigInteger('amount');
            $table->string('method', 20);
            $table->string('reference', 60)->nullable();
            $table->dateTime('paid_at');
            $table->string('notes', 300)->nullable();
            $table->foreignId('received_by')->constrained('users')->restrictOnDelete();
            // Los pagos no se borran: se anulan con motivo.
            $table->timestamp('voided_at')->nullable();
            $table->foreignId('voided_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->string('void_reason', 300)->nullable();
            $table->timestamps();

            $table->index(['patient_id', 'paid_at']);
            $table->index(['paid_at', 'method']);
        });

        Schema::create('consultations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('patient_id')->constrained()->restrictOnDelete();
            $table->foreignId('professional_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('appointment_id')->nullable()->constrained()->restrictOnDelete();
            $table->dateTime('consulted_at');
            // Campos clínicos cifrados (cast "encrypted").
            $table->text('reason');
            $table->text('subjective')->nullable();
            $table->text('objective')->nullable();
            $table->text('assessment')->nullable();
            $table->text('plan')->nullable();
            $table->text('materials')->nullable();
            $table->text('observations')->nullable();
            $table->string('next_visit', 200)->nullable();
            $table->date('next_visit_on')->nullable();
            $table->boolean('anesthesia_used')->default(false);
            // Regla 6: constancia de que se revisaron las alergias antes de anestesiar.
            $table->timestamp('allergies_acknowledged_at')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['patient_id', 'consulted_at']);
            $table->index(['professional_id', 'consulted_at']);
        });

        ImmutableTable::protect('consultations');

        Schema::create('consultation_procedures', function (Blueprint $table) {
            $table->id();
            $table->foreignId('consultation_id')->constrained()->restrictOnDelete();
            $table->foreignId('procedure_id')->constrained()->restrictOnDelete();
            $table->foreignId('treatment_item_id')->nullable()->constrained()->restrictOnDelete();
            $table->unsignedTinyInteger('tooth_code')->nullable();
            $table->foreign('tooth_code')->references('code')->on('teeth')->restrictOnDelete();
            $table->string('surface', 12)->nullable();
            $table->string('notes', 300)->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index('procedure_id');
        });

        ImmutableTable::protect('consultation_procedures');

        Schema::create('consultation_addenda', function (Blueprint $table) {
            $table->id();
            $table->foreignId('consultation_id')->constrained()->restrictOnDelete();
            $table->text('body'); // cifrado
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->timestamp('created_at')->useCurrent();
        });

        ImmutableTable::protect('consultation_addenda');

        $now = now();
        $procedures = [
            // nombre, precio (Gs.), garantía (meses), anestesia, condición del odontograma
            ['Consulta y diagnóstico', 100000, 0, false, null],
            ['Limpieza (profilaxis)', 200000, 0, false, 'limpieza'],
            ['Sellante', 120000, 12, false, 'sellante'],
            ['Obturación con resina', 250000, 12, true, 'restauracion'],
            ['Endodoncia unirradicular', 900000, 24, true, 'endodoncia'],
            ['Endodoncia multirradicular', 1400000, 24, true, 'endodoncia'],
            ['Extracción simple', 250000, 0, true, 'extraccion'],
            ['Extracción de tercer molar', 700000, 0, true, 'extraccion'],
            ['Corona de porcelana', 2200000, 36, true, 'corona'],
            ['Perno colado', 500000, 24, true, 'perno'],
            ['Implante dental', 6500000, 60, true, 'implante'],
            ['Blanqueamiento', 1200000, 0, false, null],
            ['Radiografía periapical', 60000, 0, false, null],
        ];

        DB::table('procedures')->insert(array_map(fn ($p) => [
            'name' => $p[0], 'default_price' => $p[1], 'warranty_months' => $p[2], 'requires_anesthesia' => $p[3],
            'tooth_condition' => $p[4], 'is_active' => true, 'created_at' => $now, 'updated_at' => $now,
        ], $procedures));
    }

    public function down(): void
    {
        ImmutableTable::release('consultation_addenda');
        Schema::dropIfExists('consultation_addenda');
        ImmutableTable::release('consultation_procedures');
        Schema::dropIfExists('consultation_procedures');
        ImmutableTable::release('consultations');
        Schema::dropIfExists('consultations');
        Schema::dropIfExists('payments');
        Schema::dropIfExists('installments');
        ImmutableTable::release('treatment_adjustments');
        Schema::dropIfExists('treatment_adjustments');
        Schema::dropIfExists('treatment_items');
        Schema::dropIfExists('treatment_plans');
        Schema::dropIfExists('procedures');
    }
};
