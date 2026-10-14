<?php

declare(strict_types=1);

namespace Tests\Domain;

use App\Library\Domain\DocumentNumber;
use App\Library\Domain\Errors\ValidationError;
use App\Library\Domain\Member;
use App\Library\Domain\User;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Tests\Fakes\DomainFixtures as F;

final class IdentityTest extends TestCase
{
    public function test_member_is_data_and_keeps_a_normalized_name_and_document(): void
    {
        $member = new Member(F::id(100), ' María Pérez ', new DocumentNumber(' CC-100 '));
        self::assertSame('María Pérez', $member->fullName);
        self::assertSame('CC-100', $member->documentNumber->value);
        self::assertFalse(property_exists($member, 'role'));
    }

    public function test_user_normalizes_username_and_hides_hash_from_json_and_debug(): void
    {
        $marker = 'opaque-fixture-value'; // Valor falso; no es un hash operativo ni una contraseña.
        $user = new User(F::id(200), ' Ana ', $marker, 'librarian');
        self::assertSame('ana', $user->username);
        self::assertSame($marker, $user->passwordHash());
        self::assertStringNotContainsString($marker, json_encode($user, JSON_THROW_ON_ERROR));
        self::assertArrayNotHasKey('passwordHash', $user->__debugInfo());
        self::assertSame('admin', (new User(F::id(201), 'admin', $marker, 'admin'))->role);
    }

    #[DataProvider('invalidRoles')]
    public function test_only_the_two_documented_roles_are_representable(string $role): void
    {
        $this->expectException(ValidationError::class);
        new User(F::id(200), 'ana', 'opaque-fixture-value', $role);
    }

    public static function invalidRoles(): array
    {
        return [['member'], ['ADMIN'], [''], ['superadmin']];
    }

    public function test_blank_username_is_rejected(): void
    {
        $this->expectException(ValidationError::class);
        new User(F::id(200), '  ', 'opaque-fixture-value', 'librarian');
    }

    public function test_invalid_id_is_rejected_before_an_entity_is_constructed(): void
    {
        $this->expectException(ValidationError::class);
        new Member('invalid-id', 'Socio', new DocumentNumber('CC-100'));
    }
}
