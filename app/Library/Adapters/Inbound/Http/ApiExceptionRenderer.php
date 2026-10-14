<?php

namespace App\Library\Adapters\Inbound\Http;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Throwable;

final class ApiExceptionRenderer
{
    public static function render(Throwable $exception, Request $request): JsonResponse
    {
        $status = $exception instanceof HttpExceptionInterface ? $exception->getStatusCode() : 500;
        [$code, $message] = match ($status) {
            404 => ['NOT_FOUND', 'No se encontró el recurso solicitado.'],
            405 => ['METHOD_NOT_ALLOWED', 'El método no está permitido para esta ruta.'],
            default => ['INTERNAL_ERROR', 'Ocurrió un error interno.'],
        };

        // Solo se preserva Allow, requerido por HTTP 405; nunca cabeceras arbitrarias del error.
        $headers = $status === 405 && $exception instanceof HttpExceptionInterface
            ? array_intersect_key($exception->getHeaders(), ['Allow' => true])
            : [];

        return new JsonResponse([
            'code' => $code,
            'message' => $message,
            'details' => [],
        ], $status, $headers);
    }
}
