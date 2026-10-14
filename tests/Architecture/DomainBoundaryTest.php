<?php

declare(strict_types=1);

namespace Tests\Architecture;

use PHPUnit\Framework\TestCase;

final class DomainBoundaryTest extends TestCase
{
    public function test_domain_only_references_its_own_namespace_and_native_php_types(): void
    {
        $directory = new \RecursiveDirectoryIterator(__DIR__.'/../../app/Library/Domain');
        foreach (new \RecursiveIteratorIterator($directory) as $file) {
            if ($file->isFile() && $file->getExtension() === 'php') {
                self::assertSame([], self::externalNames(file_get_contents($file->getPathname())), $file->getPathname());
            }
        }
    }

    public function test_boundary_check_detects_unused_framework_and_application_imports(): void
    {
        self::assertSame(['Illuminate\\Support\\Facades\\DB', 'App\\Library\\Application\\Ports\\Clock'],
            self::externalNames('<?php use Illuminate\\Support\\Facades\\DB; use App\\Library\\Application\\Ports\\Clock;'));
        self::assertSame([], self::externalNames('<?php use DateTimeImmutable; use App\\Library\\Domain\\Book;'));
    }

    public function test_domain_runs_in_a_process_without_composer_or_laravel(): void
    {
        $output = [];
        exec(escapeshellarg(PHP_BINARY).' '.escapeshellarg(__DIR__.'/../Fixtures/domain-isolation.php').' 2>&1', $output, $status);
        self::assertSame(0, $status, implode("\n", $output));
        self::assertSame(['domain-ok'], $output);
    }

    private static function externalNames(string $source): array
    {
        $external = [];
        foreach (token_get_all($source) as $token) {
            if (!is_array($token) || !in_array($token[0], [T_NAME_QUALIFIED, T_NAME_FULLY_QUALIFIED, T_NAME_RELATIVE], true)) {
                continue;
            }
            $name = ltrim($token[1], '\\');
            if ($name === 'App\\Library\\Domain' || str_starts_with($name, 'App\\Library\\Domain\\')) {
                continue;
            }
            if (class_exists($name, false) && (new \ReflectionClass($name))->isInternal()) {
                continue;
            }
            $external[] = $name;
        }

        return $external;
    }
}
