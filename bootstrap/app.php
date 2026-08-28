<?php

use App\Http\Middleware\ForceJsonResponse;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Response;
use Symfony\Component\HttpKernel\Exception\MethodNotAllowedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        api: __DIR__.'/../routes/api.php',
        apiPrefix: 'api/v1',
        commands: __DIR__.'/../routes/console.php',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->api(prepend: [
            ForceJsonResponse::class,
        ]);

        // Laravel 11+ no aplica limite de peticiones al grupo "api" por
        // defecto; hay que pedirlo explicitamente.
        $middleware->throttleApi();
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // Esta aplicacion es solo API: no existe respuesta de error en HTML.
        $exceptions->shouldRenderJsonWhen(fn () => true);

        $exceptions->render(function (NotFoundHttpException $e) {
            // Se distingue "el recurso no existe" de "esa ruta no existe":
            // al cliente le sirven cosas distintas segun el caso.
            return response()->json([
                'message' => $e->getPrevious() instanceof ModelNotFoundException
                    ? 'The requested resource was not found.'
                    : 'The requested endpoint does not exist.',
            ], Response::HTTP_NOT_FOUND);
        });

        $exceptions->render(function (MethodNotAllowedHttpException $e) {
            return response()->json([
                'message' => 'The HTTP method is not supported for this endpoint.',
            ], Response::HTTP_METHOD_NOT_ALLOWED);
        });
    })->create();
