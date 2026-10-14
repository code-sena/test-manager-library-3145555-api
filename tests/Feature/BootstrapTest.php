<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Route;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Tests\TestCase;

final class BootstrapTest extends TestCase
{
    public function test_health_is_public_and_does_not_require_a_database(): void
    {
        $this->get('/api/v1/health')
            ->assertOk()
            ->assertHeader('Content-Type', 'application/json')
            ->assertExactJson(['status' => 'ok']);
    }

    #[DataProvider('missingRoutes')]
    public function test_missing_routes_use_the_contract_without_an_accept_header(string $path): void
    {
        $this->get($path)->assertNotFound()->assertExactJson([
            'code' => 'NOT_FOUND',
            'message' => 'No se encontró el recurso solicitado.',
            'details' => [],
        ]);
    }

    public static function missingRoutes(): array
    {
        return [['/'], ['/up'], ['/health'], ['/api/api/v1/health'], ['/api/v1/missing']];
    }

    public function test_unsupported_health_method_preserves_the_allow_header(): void
    {
        $this->post('/api/v1/health')
            ->assertStatus(405)
            ->assertHeader('Allow', 'GET, HEAD')
            ->assertExactJson([
                'code' => 'METHOD_NOT_ALLOWED',
                'message' => 'El método no está permitido para esta ruta.',
                'details' => [],
            ]);
    }

    public function test_unexpected_errors_hide_internal_details_even_with_debug_enabled(): void
    {
        config(['app.debug' => true]);
        Route::get('/api/v1/test-error', fn () => throw new RuntimeException('private server details'));

        $this->get('/api/v1/test-error')->assertStatus(500)->assertExactJson([
            'code' => 'INTERNAL_ERROR',
            'message' => 'Ocurrió un error interno.',
            'details' => [],
        ]);
    }
}
