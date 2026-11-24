<?php

namespace App\Console\Commands;

use App\Services\Reminders\ReminderDispatcher;
use App\Services\Reminders\ReminderPlanner;
use Illuminate\Console\Command;

class RunRemindersCommand extends Command
{
    protected $signature = 'reminders:run';

    protected $description = 'Planifica los recordatorios periódicos y encola los que vencieron';

    public function handle(ReminderPlanner $planner, ReminderDispatcher $dispatcher): int
    {
        $planned = $planner->planRecurring();
        $queued = $dispatcher->dispatchDue();

        $this->info(sprintf(
            'Nuevos: %d de limpieza, %d de control, %d de garantía, %d de cumpleaños. Encolados para enviar: %d.',
            $planned['cleaning'], $planned['control'], $planned['warranty'], $planned['birthday'], $queued,
        ));

        return self::SUCCESS;
    }
}
