<?php

use App\Http\Middleware\SetLocale;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

$isApi = fn (Request $request) => $request->is('api/*') || $request->expectsJson();

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        apiPrefix: 'api/v1',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) use ($isApi): void {
        // Global (not only the api group) so it runs before auth / throttle and
        // also for unknown URLs: every error message comes back translated.
        $middleware->append(SetLocale::class);

        // There is no web login page; API guests get a JSON 401 instead of a redirect.
        $middleware->redirectGuestsTo(fn (Request $request) => $isApi($request) ? null : '/');
    })
    ->withExceptions(function (Exceptions $exceptions) use ($isApi): void {
        $exceptions->shouldRenderJsonWhen($isApi);

        $exceptions->render(function (AuthenticationException $e, Request $request) use ($isApi) {
            if ($isApi($request)) {
                return response()->json(['message' => __('Unauthenticated.')], Response::HTTP_UNAUTHORIZED);
            }
        });

        // 403 / 404 / 405 / 409 / 429 …: { message } in the request language.
        // 404 never names the model or route, so it cannot leak what exists.
        $exceptions->render(function (HttpExceptionInterface $e, Request $request) use ($isApi) {
            if (! $isApi($request)) {
                return null;
            }

            $status = $e->getStatusCode();
            $message = $e instanceof NotFoundHttpException || $e->getMessage() === ''
                ? Response::$statusTexts[$status] ?? 'Error'
                : $e->getMessage();

            return response()->json(['message' => __($message)], $status, $e->getHeaders());
        });
    })->create();
