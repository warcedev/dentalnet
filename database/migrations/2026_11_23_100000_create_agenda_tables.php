<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Consultorios (sillones/boxes dentro de la clínica).
        Schema::create('rooms', function (Blueprint $table) {
            $table->id();
            $table->string('name', 60)->unique();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('appointment_types', function (Blueprint $table) {
            $table->id();
            $table->string('name', 80)->unique();
            $table->unsignedSmallInteger('duration_minutes');
            $table->string('color', 7)->default('#0e7c86');
            // Marca los turnos de limpieza (para no recordar una limpieza ya agendada).
            $table->boolean('is_cleaning')->default(false);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // Las fechas se guardan en hora local (America/Asuncion, sin horario de verano).
        Schema::create('appointments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('patient_id')->constrained()->restrictOnDelete();
            $table->foreignId('professional_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('room_id')->constrained()->restrictOnDelete();
            $table->foreignId('appointment_type_id')->constrained()->restrictOnDelete();
            $table->dateTime('starts_at');
            $table->dateTime('ends_at');
            $table->string('status', 20)->default('programada');
            $table->text('notes')->nullable(); // cifrado
            $table->timestamp('confirmed_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->text('cancellation_reason')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamps();

            $table->index(['professional_id', 'starts_at']);
            $table->index(['room_id', 'starts_at']);
            $table->index(['patient_id', 'starts_at']);
            $table->index(['starts_at', 'status']);
        });

        // Bloqueos de horario: de un profesional, de un consultorio o de toda la clínica.
        Schema::create('schedule_blocks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('professional_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->foreignId('room_id')->nullable()->constrained()->restrictOnDelete();
            $table->dateTime('starts_at');
            $table->dateTime('ends_at');
            $table->string('reason', 160);
            $table->foreignId('created_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamps();

            $table->index(['starts_at', 'ends_at']);
        });

        Schema::create('waiting_list', function (Blueprint $table) {
            $table->id();
            $table->foreignId('patient_id')->constrained()->restrictOnDelete();
            $table->foreignId('appointment_type_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('professional_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->string('preference', 200)->nullable();
            $table->text('notes')->nullable();
            $table->string('status', 20)->default('en_espera');
            $table->foreignId('appointment_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamps();

            $table->index(['status', 'created_at']);
        });

        $now = now();

        DB::table('rooms')->insert([
            ['name' => 'Consultorio 1', 'is_active' => true, 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'Consultorio 2', 'is_active' => true, 'created_at' => $now, 'updated_at' => $now],
        ]);

        $types = [
            ['Consulta / control', 30, '#0e7c86', false],
            ['Limpieza', 45, '#16a34a', true],
            ['Obturación', 45, '#2563eb', false],
            ['Endodoncia', 90, '#7c3aed', false],
            ['Extracción', 45, '#dc2626', false],
            ['Urgencia', 30, '#ea580c', false],
            ['Ortodoncia (control)', 20, '#db2777', false],
        ];

        DB::table('appointment_types')->insert(array_map(fn ($t) => [
            'name' => $t[0], 'duration_minutes' => $t[1], 'color' => $t[2], 'is_cleaning' => $t[3],
            'is_active' => true, 'created_at' => $now, 'updated_at' => $now,
        ], $types));
    }

    public function down(): void
    {
        Schema::dropIfExists('waiting_list');
        Schema::dropIfExists('schedule_blocks');
        Schema::dropIfExists('appointments');
        Schema::dropIfExists('appointment_types');
        Schema::dropIfExists('rooms');
    }
};
