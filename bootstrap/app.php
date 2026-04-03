<?php

use App\Console\Commands\SyncDriverData;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

return Application::configure(basePath: dirname(__DIR__))
    ->withCommands([
        SyncDriverData::class,
    ])
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        //
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->render(function (\Throwable $exception, Request $request) {
            if (! $request->is('api/*')) {
                return null;
            }

            if ($exception instanceof AuthenticationException) {
                return response()->json([
                    'message' => 'Unauthenticated.',
                ], 401);
            }

            if ($exception instanceof ValidationException) {
                return response()->json([
                    'status' => false,
                    'message' => 'Validation failed',
                    'data' => [
                        'errors' => $exception->errors(),
                    ],
                ], 422);
            }

            if ($exception instanceof QueryException) {
                Log::error('Database exception', [
                    'message' => $exception->getMessage(),
                    'path' => $request->path(),
                ]);

                return response()->json([
                    'status' => false,
                    'message' => 'Database operation failed',
                    'data' => null,
                ], 500);
            }

            Log::error('Unhandled API exception', [
                'exception' => $exception,
                'path' => $request->path(),
            ]);

            $status = $exception instanceof HttpExceptionInterface
                ? $exception->getStatusCode()
                : 500;

            return response()->json([
                'status' => false,
                'message' => $status >= 500 ? 'Server error' : $exception->getMessage(),
                'data' => null,
            ], $status);
        });
    })->create();
