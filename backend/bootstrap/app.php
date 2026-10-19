<?php

use App\Http\Controllers\Shared\CustomResponseController;
use App\Http\Middleware\SimulateLatency;
use App\Models\Shared\CustomConstants;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\MethodNotAllowedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->api(append: [SimulateLatency::class]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // Keep the {status, data} envelope for API errors too
        $resp = new CustomResponseController();

        $exceptions->render(function (AuthenticationException $e, Request $request) use ($resp) {
            if ($request->is('api/*')) {
                return $resp->response(CustomConstants::RESPONSE_STATUS_UNAUTHORIZED, CustomConstants::RESPONSE_MESSAGE_UNAUTHORIZED, null, 401);
            }
        });

        $exceptions->render(function (NotFoundHttpException|MethodNotAllowedHttpException $e, Request $request) use ($resp) {
            if ($request->is('api/*')) {
                return $resp->response(CustomConstants::RESPONSE_STATUS_RECORD_NOT_FOUND, 'Endpoint not found', null, 404);
            }
        });
    })->create();
