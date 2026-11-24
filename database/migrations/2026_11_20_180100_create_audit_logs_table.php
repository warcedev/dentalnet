<?php

use App\Support\Database\ImmutableTable;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();
            // Sin FK con borrado en cascada: el registro debe sobrevivir al usuario.
            $table->foreignId('user_id')->nullable()->constrained()->restrictOnDelete();
            $table->string('event', 40);
            $table->nullableMorphs('auditable');
            // TEXT y no JSON: MySQL normaliza el JSON y alteraría el hash.
            $table->text('old_values')->nullable();
            $table->text('new_values')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->string('user_agent', 255)->nullable();
            // Nombre de la ruta (no la URL) para no registrar datos en query strings.
            $table->string('route', 120)->nullable();
            $table->timestamp('created_at')->useCurrent();
            // Cadena de hashes: cada fila incluye el hash de la anterior.
            $table->char('previous_hash', 64)->nullable();
            $table->char('hash', 64);

            $table->index('created_at');
            $table->index(['user_id', 'created_at']);
            $table->index('event');
        });

        ImmutableTable::protect('audit_logs');
    }

    public function down(): void
    {
        ImmutableTable::release('audit_logs');
        Schema::dropIfExists('audit_logs');
    }
};
