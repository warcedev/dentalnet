<?php

namespace App\Console\Commands;

use App\Services\Audit\AuditChainVerifier;
use Illuminate\Console\Command;

class VerifyAuditLogCommand extends Command
{
    protected $signature = 'audit:verify';

    protected $description = 'Verifica que el registro de auditoría no haya sido alterado';

    public function handle(AuditChainVerifier $verifier): int
    {
        $problems = $verifier->verify();

        if ($problems === []) {
            $this->info('La auditoría está íntegra.');

            return self::SUCCESS;
        }

        $this->error('Se detectaron '.count($problems).' problema(s) en la auditoría:');
        $this->table(['Registro', 'Problema'], array_map(fn ($p) => [$p['id'], $p['problem']], $problems));

        return self::FAILURE;
    }
}
