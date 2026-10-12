<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        channels: __DIR__.'/../routes/channels.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->web(append: [
            \App\Http\Middleware\EnsureNotSuspended::class,
        ]);
        $middleware->trustProxies(at: env('TRUSTED_PROXIES'));
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // Expired or tampered verification links: explain and offer a new one
        // instead of a bare "403 Invalid signature".
        $exceptions->render(function (\Illuminate\Routing\Exceptions\InvalidSignatureException $e, Request $request) {
            if ($request->routeIs('verification.verify')) {
                return redirect()->route('verification.notice')
                    ->with('status', 'verification-link-expired');
            }
        });
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
