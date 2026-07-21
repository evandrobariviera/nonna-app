<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->trustProxies(at: '*');

        $middleware->web(append: [
            \App\Http\Middleware\SetTenant::class,
        ]);

        $middleware->alias([
            'org.admin'     => \App\Http\Middleware\EnsureOrganizationAdmin::class,
            'superadmin'    => \App\Http\Middleware\EnsureSuperAdmin::class,
            'portal'        => \App\Http\Middleware\EnsurePortalAccess::class,
            'portal.client' => \App\Http\Middleware\ResolvePortalClientContext::class,
            'not-client'    => \App\Http\Middleware\EnsureNotClient::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*'),
        );
    })->create();
