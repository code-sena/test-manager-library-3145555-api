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

    public function test_application_only_references_its_own_namespace_domain_and_native_php(): void
    {
        $allowed = ['App\\Library\\Domain', 'App\\Library\\Application'];
        $directory = new \RecursiveDirectoryIterator(__DIR__.'/../../app/Library/Application');
        foreach (new \RecursiveIteratorIterator($directory) as $file) {
            if ($file->isFile() && $file->getExtension() === 'php') {
                self::assertSame([], self::externalNames(file_get_contents($file->getPathname()), $allowed), $file->getPathname());
            }
        }
        self::assertSame(['App\\Library\\Adapters\\Outbound\\Security\\Hasher', 'Illuminate\\Support\\Facades\\Hash'],
            self::externalNames('<?php use App\\Library\\Adapters\\Outbound\\Security\\Hasher; use Illuminate\\Support\\Facades\\Hash;', $allowed));
    }

    private static function externalNames(string $source, array $allowed = ['App\\Library\\Domain']): array
    {
        $external = [];
        foreach (token_get_all($source) as $token) {
            if (!is_array($token) || !in_array($token[0], [T_NAME_QUALIFIED, T_NAME_FULLY_QUALIFIED, T_NAME_RELATIVE], true)) {
                continue;
            }
            $name = ltrim($token[1], '\\');
            $permitted = false;
            foreach ($allowed as $prefix) {
                $permitted = $permitted || $name === $prefix || str_starts_with($name, $prefix.'\\');
            }
            if ($permitted) {
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
