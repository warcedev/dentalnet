<?php

use App\Services\Backup\BackupService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;

it('activa las claves foráneas, WAL y busy_timeout en SQLite', function () {
    $path = storage_path('framework/testing/pragmas.sqlite');
    File::ensureDirectoryExists(dirname($path));
    File::delete($path);
    touch($path);

    config(['database.connections.pragmas' => array_merge(config('database.connections.sqlite'), ['database' => $path])]);
    $db = DB::connection('pragmas');

    expect((int) $db->selectOne('PRAGMA foreign_keys')->foreign_keys)->toBe(1)
        ->and($db->selectOne('PRAGMA journal_mode')->journal_mode)->toBe('wal')
        ->and((int) $db->selectOne('PRAGMA busy_timeout')->timeout)->toBe(5000);

    DB::purge('pragmas');
    File::delete([$path, "{$path}-wal", "{$path}-shm"]);
});

/** Base SQLite en archivo, fuera de la transacción de RefreshDatabase. */
function backupSourceConnection(): string
{
    $path = storage_path('framework/testing/backup-source.sqlite');
    File::ensureDirectoryExists(dirname($path));
    File::delete($path);
    touch($path);

    config(['database.connections.backup_source' => array_merge(config('database.connections.sqlite'), ['database' => $path])]);
    DB::purge('backup_source');
    DB::connection('backup_source')->statement('CREATE TABLE pacientes_demo (id INTEGER PRIMARY KEY, nombre TEXT)');
    DB::connection('backup_source')->insert("INSERT INTO pacientes_demo (nombre) VALUES ('Ana')");

    return 'backup_source';
}

afterEach(function () {
    DB::purge('backup_source');
    $path = storage_path('framework/testing/backup-source.sqlite');
    File::delete([$path, "{$path}-wal", "{$path}-shm"]);
});

it('genera un respaldo zip con la base y los archivos privados', function () {
    $dir = 'storage/framework/testing/backups';
    config(['clinic.backup.path' => $dir, 'clinic.backup.password' => null]);
    File::deleteDirectory(base_path($dir));

    $zipPath = app(BackupService::class)->run(backupSourceConnection());

    $zip = new ZipArchive;
    expect($zip->open($zipPath))->toBeTrue()
        ->and($zip->locateName('database.sqlite'))->not->toBeFalse();

    // La copia es una base SQLite válida con los datos.
    $restored = storage_path('framework/testing/restored.sqlite');
    File::put($restored, $zip->getFromName('database.sqlite'));
    $zip->close();
    expect((new PDO("sqlite:{$restored}"))->query('SELECT nombre FROM pacientes_demo')->fetchColumn())->toBe('Ana');
    File::delete($restored);

    File::deleteDirectory(base_path($dir));
});

it('cifra el respaldo cuando hay contraseña configurada', function () {
    $dir = 'storage/framework/testing/backups';
    config(['clinic.backup.path' => $dir, 'clinic.backup.password' => 'clave-de-respaldo']);
    File::deleteDirectory(base_path($dir));

    $zipPath = app(BackupService::class)->run(backupSourceConnection());

    $zip = new ZipArchive;
    $zip->open($zipPath);
    $stat = $zip->statName('database.sqlite');
    expect($stat['encryption_method'])->toBe(ZipArchive::EM_AES_256);
    $zip->close();

    File::deleteDirectory(base_path($dir));
});

it('rechaza respaldar desde dentro de una transacción', function () {
    expect(fn () => app(BackupService::class)->run())
        ->toThrow(RuntimeException::class, 'dentro de una transacción');
});

it('borra respaldos más viejos que la retención', function () {
    $dir = 'storage/framework/testing/backups';
    config(['clinic.backup.path' => $dir, 'clinic.backup.keep_days' => 30]);
    File::ensureDirectoryExists(base_path($dir));
    $old = base_path("{$dir}/dentalweb-20200101-000000.zip");
    $new = base_path("{$dir}/dentalweb-20991231-000000.zip");
    File::put($old, 'x');
    File::put($new, 'x');
    touch($old, now()->subDays(31)->getTimestamp());

    expect(app(BackupService::class)->prune())->toBe(1)
        ->and(File::exists($new))->toBeTrue();

    File::deleteDirectory(base_path($dir));
});
