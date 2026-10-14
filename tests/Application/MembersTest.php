<?php

declare(strict_types=1);

namespace Tests\Application;

use App\Library\Application\Services\CreateMemberService;
use App\Library\Application\Services\GetMemberService;
use App\Library\Application\Services\SearchMembersService;
use App\Library\Domain\Errors\DocumentTaken;
use App\Library\Domain\Errors\ResourceNotFound;
use App\Library\Domain\Errors\ValidationError;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Tests\Fakes\DomainFixtures as F;
use Tests\Fakes\InMemoryMemberRepository;

final class MembersTest extends TestCase
{
    public function test_create_and_get_return_domain_data_and_the_count_of_unreturned_books(): void
    {
        $repo = new InMemoryMemberRepository;
        $member = (new CreateMemberService($repo))->execute(F::id(100), ' María Pérez ', ' CC-001 ');
        $repo->unreturned[$member->id] = 3;
        $details = (new GetMemberService($repo))->execute($member->id);
        self::assertSame('María Pérez', $details->member->fullName);
        self::assertSame('CC-001', $details->member->documentNumber->value);
        self::assertSame(3, $details->activeLoans);
        self::assertCount(1, $repo->members);
    }

    public function test_duplicate_document_is_rejected_without_overwriting_the_member(): void
    {
        $repo = new InMemoryMemberRepository;
        $create = new CreateMemberService($repo);
        $create->execute(F::id(100), 'Socio original', 'CC-001');
        try {
            $create->execute(F::id(101), 'Otro socio', ' CC-001 ');
            self::fail('Debió rechazar el documento repetido.');
        } catch (DocumentTaken) {
            self::assertCount(1, $repo->members);
            self::assertSame('Socio original', $repo->members[F::id(100)]->fullName);
        }
    }

    public function test_conflict_raised_by_the_repository_is_not_hidden_by_the_precheck(): void
    {
        $repo = new InMemoryMemberRepository;
        $repo->conflictOnAdd = true;
        $this->expectException(DocumentTaken::class);
        (new CreateMemberService($repo))->execute(F::id(100), 'Socio', 'CC-001');
    }

    public function test_search_by_name_or_document_is_paginated_and_reports_the_full_total(): void
    {
        $repo = new InMemoryMemberRepository;
        $create = new CreateMemberService($repo);
        $create->execute(F::id(100), 'María Pérez', 'CC-001');
        $create->execute(F::id(101), 'María Gómez', 'CC-002');
        $create->execute(F::id(102), 'Otro socio', 'CC-003');
        $search = new SearchMembersService($repo);
        $page = $search->execute(' MARÍA ', 1, 1);
        self::assertSame(2, $page->total);
        self::assertCount(1, $page->items);
        self::assertSame(1, $page->page);
        self::assertSame(1, $page->size);
        self::assertSame('CC-003', $search->execute('003')->items[0]->documentNumber->value);
        $emptyPage = $search->execute('María', 3, 1);
        self::assertSame([], $emptyPage->items);
        self::assertSame(2, $emptyPage->total);
        self::assertSame(0, $search->execute('sin coincidencias')->total);
    }

    #[DataProvider('invalidPagination')]
    public function test_invalid_pagination_is_rejected_before_the_repository(int $page, int $size): void
    {
        $this->expectException(ValidationError::class);
        (new SearchMembersService(new InMemoryMemberRepository))->execute('', $page, $size);
    }

    public static function invalidPagination(): array
    {
        return [[0, 20], [1, 0], [1, 51]];
    }

    public function test_missing_member_has_its_own_error(): void
    {
        $this->expectException(ResourceNotFound::class);
        (new GetMemberService(new InMemoryMemberRepository))->execute(F::id(100));
    }
}
