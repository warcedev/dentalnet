<?php

namespace App\Providers;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\DevCommands;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Vite;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureDefaults();

        // Permite levantar un servidor de prueba con los archivos compilados aunque
        // haya un Vite de desarrollo registrado en public/hot (VITE_HOT_FILE=...).
        if (is_string($hotFile = config('clinic.vite_hot_file')) && $hotFile !== '') {
            Vite::useHotFile($hotFile);
        }

        // `composer dev` también corre el programador de tareas, para que en
        // desarrollo se procesen los recordatorios como en producción.
        if ($this->app->runningInConsole() && $this->app->isLocal()) {
            DevCommands::artisan('schedule:work', 'scheduler')->green();
        }
    }

    /**
     * Configure default behaviors for production-ready applications.
     */
    protected function configureDefaults(): void
    {
        Date::use(CarbonImmutable::class);

        // En desarrollo, detectar consultas N+1 y asignaciones silenciosas.
        Model::shouldBeStrict(! app()->isProduction());

        DB::prohibitDestructiveCommands(
            app()->isProduction(),
        );

        Password::defaults(fn (): ?Password => app()->isProduction()
            ? Password::min(12)
                ->mixedCase()
                ->letters()
                ->numbers()
                ->symbols()
                ->uncompromised()
            : null,
        );
    }
}
