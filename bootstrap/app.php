<?php

use App\Http\Middleware\EnsureUserIsActive;
use App\Http\Middleware\HandleAppearance;
use App\Http\Middleware\HandleInertiaRequests;
use App\Http\Middleware\RequireAdminTwoFactor;
use App\Http\Middleware\SecurityHeaders;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->encryptCookies(except: ['appearance', 'sidebar_state']);

        // Los invitados del portal van a su propio inicio de sesión.
        $middleware->redirectGuestsTo(fn (Request $request) => $request->is('portal', 'portal/*') ? route('portal.login') : route('login'));
        $middleware->redirectUsersTo(fn (Request $request) => $request->is('portal', 'portal/*') ? route('portal.home') : route('dashboard'));

        $middleware->web(append: [
            HandleAppearance::class,
            HandleInertiaRequests::class,
            AddLinkHeadersForPreloadedAssets::class,
            SecurityHeaders::class,
            EnsureUserIsActive::class,
            RequireAdminTwoFactor::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );

        // Páginas de error en español con el diseño de la aplicación. En
        // desarrollo, los errores 500 siguen mostrando el detalle técnico.
        $exceptions->respond(function (Response $response, Throwable $e, Request $request) {
            $status = $response->getStatusCode();

            if ($status === 419) {
                Inertia::flash('toast', ['type' => 'warning', 'message' => 'La sesión expiró por inactividad. Vuelva a intentarlo.']);

                return back();
            }

            $handled = [403, 404, 429, 503];

            if (! app()->hasDebugModeEnabled()) {
                $handled[] = 500;
            }

            if (in_array($status, $handled, true) && ! $request->expectsJson()) {
                return Inertia::render('error', ['status' => $status])
                    ->toResponse($request)
                    ->setStatusCode($status);
            }

            return $response;
        });
    })->create();
