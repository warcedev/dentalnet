<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reminders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('patient_id')->constrained()->restrictOnDelete();
            $table->string('type', 30);
            $table->string('channel', 20);
            // Cita u otro origen del recordatorio (opcional).
            $table->nullableMorphs('remindable');
            $table->dateTime('scheduled_for');
            $table->string('status', 20)->default('pendiente');
            $table->unsignedTinyInteger('attempts')->default(0);
            $table->text('last_error')->nullable();
            $table->timestamp('sent_at')->nullable();
            // Evita duplicados: el mismo recordatorio nunca se crea dos veces.
            $table->string('idempotency_key', 120)->unique();
            $table->timestamps();

            $table->index(['status', 'scheduled_for']);
            $table->index(['patient_id', 'type']);
        });

        Schema::create('reminder_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('reminder_id')->constrained()->restrictOnDelete();
            $table->unsignedTinyInteger('attempt');
            $table->string('status', 20);
            $table->string('channel', 20);
            $table->string('message', 500)->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index('reminder_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reminder_logs');
        Schema::dropIfExists('reminders');
    }
};
