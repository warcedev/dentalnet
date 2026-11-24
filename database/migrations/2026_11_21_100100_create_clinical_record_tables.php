<?php

use App\Support\Database\ImmutableTable;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Antecedentes médicos versionados: cada cambio es una versión nueva.
        // Los textos se guardan cifrados (casts "encrypted").
        Schema::create('medical_histories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('patient_id')->constrained()->restrictOnDelete();
            $table->unsignedInteger('version');
            $table->text('medical_background')->nullable();
            $table->text('surgical_history')->nullable();
            $table->text('current_medication')->nullable();
            $table->text('habits')->nullable();
            $table->text('observations')->nullable();
            $table->text('change_reason')->nullable();
            $table->foreignId('recorded_by')->constrained('users')->restrictOnDelete();
            $table->timestamp('created_at')->useCurrent();

            $table->unique(['patient_id', 'version']);
        });

        ImmutableTable::protect('medical_histories');

        Schema::create('allergies', function (Blueprint $table) {
            $table->id();
            $table->foreignId('patient_id')->constrained()->restrictOnDelete();
            $table->string('type', 20);
            $table->text('allergen'); // cifrado
            $table->string('severity', 20);
            $table->text('reaction')->nullable(); // cifrado
            $table->boolean('is_active')->default(true);
            $table->foreignId('recorded_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('deactivated_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('deactivated_at')->nullable();
            $table->text('deactivation_reason')->nullable();
            $table->timestamps();

            $table->index(['patient_id', 'is_active']);
        });

        Schema::create('patient_conditions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('patient_id')->constrained()->restrictOnDelete();
            $table->string('condition', 40);
            $table->text('notes')->nullable(); // cifrado
            $table->boolean('is_active')->default(true);
            $table->foreignId('recorded_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('deactivated_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('deactivated_at')->nullable();
            $table->text('deactivation_reason')->nullable();
            $table->timestamps();

            $table->index(['patient_id', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('patient_conditions');
        Schema::dropIfExists('allergies');
        ImmutableTable::release('medical_histories');
        Schema::dropIfExists('medical_histories');
    }
};
