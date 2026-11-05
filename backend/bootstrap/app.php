<?php

use App\Http\Middleware\AssignRequestId;
use App\Http\Middleware\RequireMfa;
use App\Http\Middleware\RequirePermission;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        apiPrefix: 'api/v1',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->append(AssignRequestId::class);
        $middleware->appendToGroup('api', [ThrottleRequests::class.':api']);
        $middleware->alias(['permission' => RequirePermission::class, 'mfa' => RequireMfa::class]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
        $exceptions->render(function (Throwable $exception, Request $request) {
            if (! $request->is('api/*')) {
                return null;
            }

            $status = match (true) {
                $exception instanceof AuthenticationException => 401,
                $exception instanceof AuthorizationException => 403,
                $exception instanceof ValidationException => 422,
                $exception instanceof HttpExceptionInterface => $exception->getStatusCode(),
                default => 500,
            };
            $message = match ($status) {
                401 => 'Unauthenticated.',
                403 => 'This action is unauthorized.',
                404 => 'The requested resource was not found.',
                409 => 'The request conflicts with the current resource state.',
                422 => 'The given data was invalid.',
                429 => 'Too many requests.',
                default => $status >= 500 ? 'An unexpected error occurred.' : 'The request could not be completed.',
            };

            return response()->json([
                'success' => false,
                'message' => $message,
                'data' => null,
                'errors' => $exception instanceof ValidationException ? $exception->errors() : null,
                'meta' => ['request_id' => $request->attributes->get('request_id')],
            ], $status);
        });
    })->create();
