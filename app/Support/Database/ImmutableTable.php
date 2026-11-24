<?php

namespace App\Support\Database;

use Illuminate\Database\Connection;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use RuntimeException;

/**
 * Protege una tabla a nivel de base de datos para que solo admita INSERT.
 *
 * Es la segunda barrera (la primera es el modelo Eloquent): impide que una
 * consulta directa, un `DB::table()->update()` o una herramienta externa
 * modifique o borre registros históricos.
 *
 * Soporta SQLite, MySQL/MariaDB y PostgreSQL para permitir la migración de
 * motor sin reescribir la lógica. Las tablas protegibles están en una lista
 * blanca: el SQL de los triggers nunca se arma con texto arbitrario.
 */
final class ImmutableTable
{
    public static function protect(string $table, ?string $connection = null): void
    {
        /** @var Connection $db */
        $db = DB::connection($connection);
        $name = self::identifier($table);

        foreach (['update', 'delete'] as $operation) {
            $op = $operation === 'update' ? 'UPDATE' : 'DELETE';
            $trigger = $name.'_no_'.$operation;
            $message = 'La tabla '.$name.' es de solo insercion';

            match ($db->getDriverName()) {
                'sqlite' => $db->unprepared(
                    'CREATE TRIGGER '.$trigger.' BEFORE '.$op.' ON '.$name
                    .' BEGIN SELECT RAISE(ABORT, \''.$message.'\'); END;'
                ),
                'mysql', 'mariadb' => $db->unprepared(
                    'CREATE TRIGGER '.$trigger.' BEFORE '.$op.' ON '.$name.' FOR EACH ROW '
                    .'SIGNAL SQLSTATE \'45000\' SET MESSAGE_TEXT = \''.$message.'\';'
                ),
                'pgsql' => self::protectPostgres($db, $name, $trigger, $op, $message),
                default => throw new RuntimeException('Motor no soportado: '.$db->getDriverName()),
            };
        }
    }

    public static function release(string $table, ?string $connection = null): void
    {
        /** @var Connection $db */
        $db = DB::connection($connection);
        $name = self::identifier($table);

        foreach (['update', 'delete'] as $operation) {
            $trigger = $name.'_no_'.$operation;

            if ($db->getDriverName() === 'pgsql') {
                $db->unprepared('DROP TRIGGER IF EXISTS '.$trigger.' ON '.$name.';');
                $db->unprepared('DROP FUNCTION IF EXISTS '.$trigger.'_fn();');
            } else {
                $db->unprepared('DROP TRIGGER IF EXISTS '.$trigger.';');
            }
        }
    }

    /**
     * @param  literal-string  $table
     * @param  literal-string  $trigger
     * @param  literal-string  $operation
     * @param  literal-string  $message
     */
    private static function protectPostgres(Connection $db, string $table, string $trigger, string $operation, string $message): bool
    {
        $db->unprepared(
            'CREATE OR REPLACE FUNCTION '.$trigger.'_fn() RETURNS trigger AS $$ '
            .'BEGIN RAISE EXCEPTION \''.$message.'\'; END; $$ LANGUAGE plpgsql;'
        );

        return $db->unprepared(
            'CREATE TRIGGER '.$trigger.' BEFORE '.$operation.' ON '.$table
            .' FOR EACH ROW EXECUTE FUNCTION '.$trigger.'_fn();'
        );
    }

    /**
     * Lista blanca de tablas de solo inserción. Agregar aquí las nuevas
     * (p. ej. tooth_events en la Fase 3).
     *
     * @return literal-string
     */
    private static function identifier(string $table): string
    {
        return match ($table) {
            'audit_logs' => 'audit_logs',
            'medical_histories' => 'medical_histories',
            'consent_signatures' => 'consent_signatures',
            'tooth_events' => 'tooth_events',
            'treatment_adjustments' => 'treatment_adjustments',
            'consultations' => 'consultations',
            'consultation_procedures' => 'consultation_procedures',
            'consultation_addenda' => 'consultation_addenda',
            'prescriptions' => 'prescriptions',
            'periodontal_records' => 'periodontal_records',
            'periodontal_measurements' => 'periodontal_measurements',
            default => throw new InvalidArgumentException("La tabla {$table} no está en la lista de tablas inmutables."),
        };
    }
}
