<?php

declare(strict_types=1);

namespace App\Adapters\Inbound\Http\Middleware;

use App\Adapters\Inbound\Http\Errors\Unauthenticated;
use App\Application\Dto\TokenClaims;
use App\Application\Ports\Outbound\TokenVerifier;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Requires a valid bearer token and leaves its claims on the request for the role check and the controllers.
 */
final class AuthenticateJwt
{
    public const CLAIMS_ATTRIBUTE = 'token_claims';

    public function __construct(private readonly TokenVerifier $verifier)
    {
    }

    public function handle(Request $request, Closure $next): Response
    {
        $token = $request->bearerToken();
        $claims = $token === null ? null : $this->verifier->verify($token);
        if ($claims === null) {
            throw new Unauthenticated();
        }
        $request->attributes->set(self::CLAIMS_ATTRIBUTE, $claims);

        return $next($request);
    }

    public static function claimsOf(Request $request): TokenClaims
    {
        return $request->attributes->get(self::CLAIMS_ATTRIBUTE);
    }
}
