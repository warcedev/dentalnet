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
        // Catálogo FDI: 32 permanentes (11–48) y 20 temporales (51–85).
        Schema::create('teeth', function (Blueprint $table) {
            $table->unsignedTinyInteger('code')->primary();
            $table->string('dentition', 12); // permanente | temporal
            $table->unsignedTinyInteger('quadrant');
            $table->unsignedTinyInteger('position');
            $table->string('arch', 10); // superior | inferior
            $table->string('type', 12); // incisivo | canino | premolar | molar
        });

        DB::table('teeth')->insert($this->catalog());

        // Historial del odontograma: solo inserciones.
        Schema::create('tooth_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('patient_id')->constrained()->restrictOnDelete();
            // Nulo para procedimientos de toda la boca (limpieza).
            $table->unsignedTinyInteger('tooth_code')->nullable();
            $table->foreign('tooth_code')->references('code')->on('teeth')->restrictOnDelete();
            $table->string('surface', 12)->nullable();
            $table->string('condition', 20);
            $table->string('status', 20);
            $table->date('detected_on');
            $table->date('treated_on')->nullable();
            $table->foreignId('professional_id')->constrained('users')->restrictOnDelete();
            // La FK a consultas se agrega en la Fase 5, cuando exista la tabla.
            $table->unsignedBigInteger('consultation_id')->nullable();
            $table->text('notes')->nullable(); // cifrado
            // Corrección: este evento reemplaza a otro, que deja de contar.
            $table->foreignId('corrects_event_id')->nullable()->constrained('tooth_events')->restrictOnDelete();
            $table->text('correction_reason')->nullable(); // cifrado
            // Anulación: el evento corregido se había cargado por error.
            $table->boolean('is_void')->default(false);
            $table->timestamp('created_at')->useCurrent();

            $table->index(['patient_id', 'tooth_code']);
            $table->index(['patient_id', 'condition', 'status']);
            $table->index(['patient_id', 'detected_on']);
            $table->unique('corrects_event_id');
        });

        ImmutableTable::protect('tooth_events');
    }

    public function down(): void
    {
        ImmutableTable::release('tooth_events');
        Schema::dropIfExists('tooth_events');
        Schema::dropIfExists('teeth');
    }

    /** @return list<array<string, int|string>> */
    private function catalog(): array
    {
        $rows = [];

        foreach ([1, 2, 3, 4, 5, 6, 7, 8] as $quadrant) {
            $permanent = $quadrant <= 4;
            $max = $permanent ? 8 : 5;

            for ($position = 1; $position <= $max; $position++) {
                $rows[] = [
                    'code' => $quadrant * 10 + $position,
                    'dentition' => $permanent ? 'permanente' : 'temporal',
                    'quadrant' => $quadrant,
                    'position' => $position,
                    'arch' => in_array($quadrant, [1, 2, 5, 6], true) ? 'superior' : 'inferior',
                    'type' => match (true) {
                        $position <= 2 => 'incisivo',
                        $position === 3 => 'canino',
                        $permanent && $position <= 5 => 'premolar',
                        default => 'molar',
                    },
                ];
            }
        }

        return $rows;
    }
};
