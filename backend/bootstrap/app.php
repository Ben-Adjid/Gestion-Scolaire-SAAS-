<?php

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->redirectGuestsTo(fn () => null);

        $middleware->alias([
            'role' => \App\Http\Middleware\CheckRole::class,
            'subscription' => \App\Http\Middleware\EnsureActiveSubscription::class,
            'enforce_tenant' => \App\Http\Middleware\EnforceTenantAccess::class,
            'plan' => \App\Http\Middleware\CheckPlan::class,
            'reserve_parents' => \App\Http\Middleware\ReserveAuxParents::class,
            'perm_or_role' => \App\Http\Middleware\AutoriserRoleOuPermission::class,
            'donnees_scolaires' => \App\Http\Middleware\ProtegerDonneesScolaires::class,
            'mot_de_passe_a_jour' => \App\Http\Middleware\ExigerChangementMotDePasse::class,
        ]);

        $middleware->append(\App\Http\Middleware\SecurityHeaders::class);
        $middleware->throttleApi();
    })
    ->withExceptions(function (Exceptions $exceptions) {
        $exceptions->shouldRenderJsonWhen(fn (Request $request, Throwable $e) => $request->is('api/*') || $request->expectsJson());

        $exceptions->render(function (AuthenticationException $e, Request $request) {
            if ($request->is('api/*') || $request->expectsJson()) {
                return response()->json(['message' => 'Non authentifié.'], 401);
            }
        });

        $exceptions->render(function (AuthorizationException|AccessDeniedHttpException $e, Request $request) {
            if ($request->is('api/*') || $request->expectsJson()) {
                return response()->json([
                    'message' => ($e->getMessage() && $e->getMessage() !== 'This action is unauthorized.')
                        ? $e->getMessage()
                        : "Vous n'avez pas les droits nécessaires pour cette action.",
                ], 403);
            }
        });
    })->create();
