<?php

use App\Http\Middleware\HandleAppearance;
use App\Http\Middleware\HandleInertiaRequests;
use App\Http\Middleware\SetActiveCompany;
use App\Http\Middleware\SetCompanyFromRequest;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets;
use Illuminate\Http\Request;
use Illuminate\Routing\Middleware\SubstituteBindings;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->encryptCookies(except: ['appearance', 'sidebar_state']);

        $middleware->web(append: [
            HandleAppearance::class,
            SetActiveCompany::class,
            HandleInertiaRequests::class,
            AddLinkHeadersForPreloadedAssets::class,
        ]);

        // Der Firmenkontext muss stehen, BEVOR Route-Model-Binding ein
        // mandantengebundenes Modell auflöst — sonst wirft der Global Scope.
        // Für die JSON-Endpunkte gilt dasselbe mit der expliziten
        // company_id aus dem Request (Abschnitt 9).
        $middleware->prependToPriorityList(SubstituteBindings::class, SetActiveCompany::class);
        $middleware->prependToPriorityList(SubstituteBindings::class, SetCompanyFromRequest::class);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
