<?php

declare(strict_types=1);

namespace App\Adapters\Inbound\Http\Controllers;

use App\Adapters\Inbound\Http\Requests\LoginRequest;
use App\Application\Services\LoginService;
use Illuminate\Http\JsonResponse;

final class AuthController
{
    private const TOKEN_TYPE = 'Bearer';

    public function login(LoginRequest $request, LoginService $service): JsonResponse
    {
        $token = $service->execute($request->validated('username'), $request->validated('password'));

        return response()->json([
            'access_token' => $token->accessToken,
            'token_type' => self::TOKEN_TYPE,
            'expires_in' => $token->expiresIn,
            'role' => $token->role->value,
        ]);
    }
}
