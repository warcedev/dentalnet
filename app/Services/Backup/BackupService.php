<?php

namespace App\Services\Backup;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use RuntimeException;
use Symfony\Component\Finder\SplFileInfo;
use ZipArchive;

/**
 * Respaldo de la base y de los archivos privados en un .zip.
 *
 * Con SQLite usa `VACUUM INTO`, que genera una copia consistente aunque la
 * base esté en uso y en modo WAL. Si se define BACKUP_PASSWORD, el .zip se
 * cifra con AES-256.
 */
class BackupService
{
    public function run(?string $connection = null): string
    {
        $directory = base_path((string) config('clinic.backup.path'));
        File::ensureDirectoryExists($directory, 0700);

        $stamp = now()->format('Ymd-His');
        $snapshot = "{$directory}/database-{$stamp}.sqlite";
        $zipPath = "{$directory}/dentalweb-{$stamp}.zip";

        $this->snapshotDatabase($snapshot, $connection);

        try {
            $this->createZip($zipPath, $snapshot);
        } finally {
            File::delete($snapshot);
        }

        chmod($zipPath, 0600);

        return $zipPath;
    }

    /** Borra los respaldos más antiguos que el período de retención. Devuelve cuántos borró. */
    public function prune(): int
    {
        $directory = base_path((string) config('clinic.backup.path'));
        $limit = now()->subDays((int) config('clinic.backup.keep_days'))->getTimestamp();
        $deleted = 0;

        foreach (File::glob("{$directory}/dentalweb-*.zip") as $file) {
            if (filemtime($file) < $limit) {
                File::delete($file);
                $deleted++;
            }
        }

        return $deleted;
    }

    private function snapshotDatabase(string $target, ?string $connectionName): void
    {
        $connection = DB::connection($connectionName);

        if ($connection->getDriverName() !== 'sqlite') {
            throw new RuntimeException('El respaldo automático solo soporta SQLite. Para MySQL/PostgreSQL use mysqldump o pg_dump.');
        }

        if ($connection->transactionLevel() > 0) {
            throw new RuntimeException('No se puede respaldar SQLite desde dentro de una transacción.');
        }

        $connection->statement('VACUUM INTO ?', [$target]);
    }

    private function createZip(string $zipPath, string $snapshot): void
    {
        $zip = new ZipArchive;

        if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException("No se pudo crear {$zipPath}");
        }

        $password = (string) config('clinic.backup.password');
        $encrypt = $password !== '' && ZipArchive::isEncryptionMethodSupported(ZipArchive::EM_AES_256);

        if ($password !== '' && ! $encrypt) {
            $zip->close();
            File::delete($zipPath);

            throw new RuntimeException('Esta instalación de PHP no soporta cifrado AES-256 en zip.');
        }

        if ($encrypt) {
            $zip->setPassword($password);
        }

        $add = function (string $file, string $name) use ($zip, $encrypt) {
            $zip->addFile($file, $name);

            if ($encrypt) {
                $zip->setEncryptionName($name, ZipArchive::EM_AES_256);
            }
        };

        $add($snapshot, 'database.sqlite');

        foreach ((array) config('clinic.backup.include') as $folder) {
            $absolute = base_path($folder);

            if (! File::isDirectory($absolute)) {
                continue;
            }

            /** @var SplFileInfo $file */
            foreach (File::allFiles($absolute) as $file) {
                $add($file->getPathname(), 'files/'.$folder.'/'.$file->getRelativePathname());
            }
        }

        $zip->close();
    }
}
