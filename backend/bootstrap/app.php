<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use App\Exceptions\BusinessException;
use App\Exceptions\ErrorCodes;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__ . '/../routes/web.php',
        api: __DIR__ . '/../routes/api.php',
        commands: __DIR__ . '/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Pure bearer token authentication - no session/CSRF needed
    
        // Register route middleware aliases
        $middleware->alias([
            'working.day' => \App\Http\Middleware\EnsureWorkingDay::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // Handle unauthenticated API requests - return JSON instead of redirect
        $exceptions->render(function (AuthenticationException $e, Request $request) {
            if ($request->expectsJson() || $request->is('api/*')) {
                return response()->json([
                    'success' => false,
                    'error' => [
                        'code' => ErrorCodes::AUTH_001,
                        'message' => ErrorCodes::getMessageEn(ErrorCodes::AUTH_001),
                        'message_ar' => ErrorCodes::getMessage(ErrorCodes::AUTH_001),
                    ]
                ], 401);
            }
        });

        // تحسين 2025-12-13: معالجة BusinessException بتنسيق JSON موحد
        $exceptions->render(function (BusinessException $e, Request $request) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => $e->getErrorCode(),
                    'message' => $e->getMessageAr(),
                    'message_en' => $e->getMessageEn(),
                ]
            ], 422);
        });
    })->create();

