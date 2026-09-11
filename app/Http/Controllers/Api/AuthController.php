<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\LoginRequest;
use App\Http\Resources\UserResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

/**
 * Авторизация SPA: сессия + Sanctum. Регистрации нет, пользователь
 * создаётся сидером.
 */
class AuthController extends Controller
{
    public function login(LoginRequest $request): JsonResponse
    {
        if (! Auth::guard('web')->attempt($request->validated())) {
            throw ValidationException::withMessages([
                'email' => 'Неверный e-mail или пароль.',
            ]);
        }

        $request->session()->regenerate();

        return response()->json([
            'user' => UserResource::make($request->user()),
        ]);
    }

    public function me(Request $request): JsonResponse
    {
        return response()->json([
            'user' => UserResource::make($request->user()),
        ]);
    }

    public function logout(Request $request): Response
    {
        Auth::guard('web')->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return response()->noContent();
    }
}
