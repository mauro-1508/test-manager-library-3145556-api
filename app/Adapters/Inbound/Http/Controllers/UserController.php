<?php

declare(strict_types=1);

namespace App\Adapters\Inbound\Http\Controllers;

use App\Adapters\Inbound\Http\Requests\CreateUserRequest;
use App\Application\Services\CreateLibrarianService;
use Illuminate\Http\JsonResponse;

final class UserController
{
    public function store(CreateUserRequest $request, CreateLibrarianService $service): JsonResponse
    {
        $user = $service->execute(
            $request->validated('username'),
            $request->validated('password'),
            $request->validated('role'),
        );

        return response()->json(
            ['id' => $user->id, 'username' => $user->username, 'role' => $user->role->value],
            201,
        );
    }
}
