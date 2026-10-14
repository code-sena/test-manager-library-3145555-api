<?php

declare(strict_types=1);

namespace Tests\Fakes;

use App\Library\Application\MemberPage;
use App\Library\Application\Ports\MemberRepository;
use App\Library\Domain\DocumentNumber;
use App\Library\Domain\Errors\DocumentTaken;
use App\Library\Domain\Member;
use LogicException;

final class InMemoryMemberRepository implements MemberRepository
{
    /** @var array<string, Member> */
    public array $members = [];
    public array $unreturned = [];
    public bool $conflictOnAdd = false;

    public function find(string $id): ?Member
    {
        return $this->members[$id] ?? null;
    }

    public function findByDocument(DocumentNumber $number): ?Member
    {
        foreach ($this->members as $member) {
            if ($member->documentNumber->value === $number->value) {
                return $member;
            }
        }

        return null;
    }

    public function add(Member $member): void
    {
        if ($this->conflictOnAdd || $this->findByDocument($member->documentNumber) !== null) {
            throw new DocumentTaken('El documento ya está registrado.');
        }
        if (isset($this->members[$member->id])) {
            throw new LogicException('La identidad asignada por el servidor ya existe.');
        }
        $this->members[$member->id] = $member;
    }

    public function search(string $query, int $page, int $size): MemberPage
    {
        $items = array_values(array_filter($this->members, fn (Member $member) => $query === ''
            || mb_stripos($member->fullName, $query, 0, 'UTF-8') !== false
            || mb_stripos($member->documentNumber->value, $query, 0, 'UTF-8') !== false));
        usort($items, fn (Member $left, Member $right) => [$left->fullName, $left->id] <=> [$right->fullName, $right->id]);

        return new MemberPage(array_slice($items, ($page - 1) * $size, $size), $page, $size, count($items));
    }

    public function countUnreturnedBooks(string $memberId): int
    {
        return $this->unreturned[$memberId] ?? 0;
    }
}
