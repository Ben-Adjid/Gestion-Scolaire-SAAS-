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

        // Surveillance : chaque erreur inattendue est enregistrée (page « Erreurs serveur ») et signalée en production.
        // Le rapport habituel dans les journaux continue normalement.
        $exceptions->report(function (Throwable $e) {
            app(\App\Services\SurveillanceErreurs::class)->enregistrer($e);
        });

        $exceptions->render(function (AuthenticationException $e, Request $request) {
            if ($request->is('api/*') || $request->expectsJson()) {
                return response()->json(['message' => 'Non authentifié.'], 401);
            }
        });

        // Messages clairs en français : jamais la page d'erreur technique de Laravel dans l'application.
        $exceptions->render(function (\Illuminate\Database\Eloquent\ModelNotFoundException|\Symfony\Component\HttpKernel\Exception\NotFoundHttpException $e, Request $request) {
            if ($request->is('api/*') || $request->expectsJson()) {
                return response()->json(['message' => 'Élément introuvable. Il a peut-être été supprimé : actualisez la page.'], 404);
            }
        });

        $exceptions->render(function (\Symfony\Component\HttpKernel\Exception\MethodNotAllowedHttpException $e, Request $request) {
            if ($request->is('api/*') || $request->expectsJson()) {
                return response()->json(['message' => 'Cette action n’est pas disponible. Actualisez la page et réessayez.'], 405);
            }
        });

        $exceptions->render(function (\Throwable $e, Request $request) {
            if (($request->is('api/*') || $request->expectsJson())
                && ! $e instanceof \Illuminate\Validation\ValidationException
                && ! $e instanceof \Symfony\Component\HttpKernel\Exception\HttpException
                && ! $e instanceof AuthenticationException
                && ! $e instanceof AuthorizationException) {
                return response()->json([
                    'message' => 'Une erreur inattendue est survenue. Réessayez ; si le problème continue, prévenez l’administrateur.',
                ], 500);
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
