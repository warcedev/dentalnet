<?php

use Illuminate\Support\Facades\Schedule;

/*
|--------------------------------------------------------------------------
| Tareas programadas
|--------------------------------------------------------------------------
|
| Requiere en el servidor una entrada de cron que ejecute cada minuto:
|   * * * * * cd /ruta/al/proyecto && php artisan schedule:run >> /dev/null 2>&1
|
*/

Schedule::command('reminders:run')->everyTenMinutes()->withoutOverlapping();
Schedule::command('clinic:backup')->dailyAt('02:00')->withoutOverlapping()->onOneServer();
Schedule::command('audit:verify')->dailyAt('03:00');
Schedule::command('queue:prune-failed --hours=720')->weekly();
