<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('phone', 30)->nullable()->after('email');
            // Registro profesional del odontólogo (aparece en recetas).
            $table->string('license_number', 50)->nullable()->after('phone');
            // Los usuarios nunca se borran (tienen historial asociado): se desactivan.
            $table->boolean('is_active')->default(true)->after('license_number');
            $table->timestamp('last_login_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['phone', 'license_number', 'is_active', 'last_login_at']);
        });
    }
};
