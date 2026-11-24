<?php

namespace App\Console\Commands;

use App\Services\Backup\BackupService;
use Illuminate\Console\Command;
use Throwable;

class BackupCommand extends Command
{
    protected $signature = 'clinic:backup {--no-prune : No borrar respaldos antiguos}';

    protected $description = 'Respalda la base de datos y los archivos privados';

    public function handle(BackupService $backups): int
    {
        try {
            $path = $backups->run();
        } catch (Throwable $e) {
            $this->error('El respaldo falló: '.$e->getMessage());
            report($e);

            return self::FAILURE;
        }

        $this->info("Respaldo creado: {$path}");

        if (! $this->option('no-prune')) {
            $this->line('Respaldos antiguos eliminados: '.$backups->prune());
        }

        return self::SUCCESS;
    }
}
