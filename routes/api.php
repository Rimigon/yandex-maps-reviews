<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\OrganizationController;
use App\Http\Controllers\Api\OrganizationReviewController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API
|--------------------------------------------------------------------------
|
| Авторизация — сессионная (Sanctum SPA), поэтому /login и /logout живут
| рядом с остальными методами и работают с cookie, а не с токенами.
|
*/

Route::post('/login', [AuthController::class, 'login'])->name('api.login');

Route::middleware('auth:sanctum')->group(function (): void {
    Route::get('/user', [AuthController::class, 'me'])->name('api.user');
    Route::post('/logout', [AuthController::class, 'logout'])->name('api.logout');

    Route::get('/organizations', [OrganizationController::class, 'index']);
    Route::post('/organizations', [OrganizationController::class, 'store']);
    Route::get('/organizations/{organization}', [OrganizationController::class, 'show']);
    Route::delete('/organizations/{organization}', [OrganizationController::class, 'destroy']);

    Route::post('/organizations/{organization}/sync', [OrganizationController::class, 'sync']);
    Route::get('/organizations/{organization}/reviews', [OrganizationReviewController::class, 'index']);
    Route::get('/organizations/{organization}/snapshots', [OrganizationController::class, 'snapshots']);
});
