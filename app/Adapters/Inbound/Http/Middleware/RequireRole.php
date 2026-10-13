<?php

declare(strict_types=1);

namespace App\Adapters\Inbound\Http\Middleware;

use App\Adapters\Inbound\Http\Errors\ForbiddenRole;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Usage: `role:admin` or `role:admin,librarian`. Runs after AuthenticateJwt.
 */
final class RequireRole
{
    public function handle(Request $request, Closure $next, string ...$allowedRoles): Response
    {
        $role = AuthenticateJwt::claimsOf($request)->role->value;
        if (!in_array($role, $allowedRoles, true)) {
            throw new ForbiddenRole();
        }

        return $next($request);
    }
}
