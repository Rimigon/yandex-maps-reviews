<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // SPA-аутентификация Sanctum: запросы с фронта приходят с сессионной
        // cookie, поэтому api-роуты должны видеть сессию.
        $middleware->statefulApi();

        // По умолчанию Laravel уводит гостя на маршрут с именем login. В этом
        // приложении такого маршрута нет (экран входа — часть SPA), из-за чего
        // любой неавторизованный запрос падал с 500 «Route [login] not defined».
        // Для API отдаём 401 (см. shouldRenderJsonWhen ниже), для остальных
        // адресов уводим на экран входа.
        $middleware->redirectGuestsTo(
            fn (Request $request) => $request->is('api/*') ? null : '/login',
        );
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // API отвечает JSON всегда, даже если клиент не прислал Accept.
        // Иначе неавторизованный запрос превращается в 500 «Route [login] not defined».
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
