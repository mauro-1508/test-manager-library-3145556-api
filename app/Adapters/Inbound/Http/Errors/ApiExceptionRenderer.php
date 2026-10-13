<?php

declare(strict_types=1);

namespace App\Adapters\Inbound\Http\Errors;

use App\Domain\DomainError;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Http\Exceptions\PostTooLargeException;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Exception\BadRequestException;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\MethodNotAllowedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Throwable;

/**
 * Registered from bootstrap/app.php: every error of the API leaves through the single {code, message, details} body.
 */
final class ApiExceptionRenderer
{
    private const MSG_NOT_FOUND = 'El recurso solicitado no existe.';
    private const MSG_METHOD_NOT_ALLOWED = 'El método no está permitido para esta ruta.';
    private const MSG_VALIDATION = 'Los datos enviados no son válidos.';
    private const MSG_MALFORMED_BODY = 'El cuerpo de la petición no es un JSON válido.';
    private const MSG_TOO_LARGE = 'El cuerpo de la petición es demasiado grande.';
    private const MSG_INTERNAL = 'Ocurrió un error inesperado. Inténtalo de nuevo más tarde.';

    public static function register(Exceptions $exceptions): void
    {
        $exceptions->dontReport([DomainError::class]);
        $exceptions->shouldRenderJsonWhen(static fn (): bool => true);
        $exceptions->render(static fn (Throwable $error): JsonResponse => (new self())->render($error));
    }

    public function render(Throwable $error): JsonResponse
    {
        return match (true) {
            $error instanceof DomainError => $this->renderBusinessError($error),
            $error instanceof ValidationException => ErrorBody::response(
                422,
                ErrorCode::VALIDATION_ERROR,
                self::MSG_VALIDATION,
                ValidationIssues::of($error),
            ),
            $error instanceof NotFoundHttpException, $error instanceof ModelNotFoundException => ErrorBody::response(
                404,
                ErrorCode::NOT_FOUND,
                self::MSG_NOT_FOUND,
            ),
            $error instanceof MethodNotAllowedHttpException => ErrorBody::response(
                405,
                ErrorCode::METHOD_NOT_ALLOWED,
                self::MSG_METHOD_NOT_ALLOWED,
            ),
            $error instanceof BadRequestHttpException, $error instanceof BadRequestException => ErrorBody::response(
                422,
                ErrorCode::VALIDATION_ERROR,
                self::MSG_MALFORMED_BODY,
            ),
            $error instanceof PostTooLargeException => ErrorBody::response(
                422,
                ErrorCode::VALIDATION_ERROR,
                self::MSG_TOO_LARGE,
            ),
            default => $this->internalError(),
        };
    }

    private function renderBusinessError(DomainError $error): JsonResponse
    {
        $status = BusinessErrorStatus::of($error);

        return $status === null
            ? $this->internalError()
            : ErrorBody::response($status, $error->errorCode(), $error->getMessage());
    }

    private function internalError(): JsonResponse
    {
        return ErrorBody::response(500, ErrorCode::INTERNAL_ERROR, self::MSG_INTERNAL);
    }
}
