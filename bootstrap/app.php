<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Đăng ký alias middleware cho toàn bộ API
        $middleware->alias([
            'force.json' => \App\Http\Middleware\ForceJsonResponse::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // Chuẩn hóa ValidationException → JSON
        $exceptions->render(function (
            \Illuminate\Validation\ValidationException $e,
            \Illuminate\Http\Request $request
        ) {
            if ($request->expectsJson()) {
                return response()->json([
                    'message' => 'Dữ liệu không hợp lệ.',
                    'errors'  => $e->errors(),
                ], 422);
            }
        });

        // ModelNotFoundException → 404 JSON
        $exceptions->render(function (
            \Illuminate\Database\Eloquent\ModelNotFoundException $e,
            \Illuminate\Http\Request $request
        ) {
            if ($request->expectsJson()) {
                return response()->json(['message' => 'Không tìm thấy dữ liệu.'], 404);
            }
        });

        // AuthenticationException → 401 JSON
        $exceptions->render(function (
            \Illuminate\Auth\AuthenticationException $e,
            \Illuminate\Http\Request $request
        ) {
            if ($request->expectsJson()) {
                return response()->json([
                    'message' => 'Chưa đăng nhập hoặc phiên làm việc đã hết hạn.',
                ], 401);
            }
        });

        // AuthorizationException → 403 JSON
        $exceptions->render(function (
            \Illuminate\Auth\Access\AuthorizationException $e,
            \Illuminate\Http\Request $request
        ) {
            if ($request->expectsJson()) {
                return response()->json(['message' => 'Không có quyền thực hiện thao tác này.'], 403);
            }
        });

        // Ẩn chi tiết lỗi 500 trên production
        $exceptions->render(function (
            \Throwable $e,
            \Illuminate\Http\Request $request
        ) {
            if ($request->expectsJson() && app()->isProduction()) {
                return response()->json(['message' => 'Đã có lỗi xảy ra. Vui lòng thử lại.'], 500);
            }
        });
    })->create();
