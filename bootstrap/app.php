<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Illuminate\Validation\ValidationException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'barbman.auth' => \App\Http\Middleware\BarbmanAuth::class,
        ]);

        $middleware->api(prepend: [
            \Illuminate\Http\Middleware\HandleCors::class,
        ]);

        // Rate limiting handled per-route, not globally (Cloudflare protects at edge)
        $middleware->api(append: []);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(fn () => true);

        $exceptions->render(function (\Throwable $e) {
            // Let validation errors pass through with field details
            if ($e instanceof ValidationException) {
                return null;
            }

            // Let HTTP exceptions pass through with their message
            if ($e instanceof HttpException) {
                return response()->json([
                    'message' => $e->getMessage() ?: 'Error',
                ], $e->getStatusCode());
            }

            // Any other exception: hide internal details
            report($e);

            return response()->json([
                'message' => 'Error interno del servidor.',
            ], 500);
        });
    })->create();
