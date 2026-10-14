<?php

declare(strict_types=1);

namespace App\Library\Application\Ports;

use App\Library\Application\MemberPage;
use App\Library\Domain\DocumentNumber;
use App\Library\Domain\Member;

interface MemberRepository
{
    public function find(string $id): ?Member;

    public function findByDocument(DocumentNumber $number): ?Member;

    /** El adaptador traduce la restricción única de documento a DocumentTaken, también ante concurrencia. */
    public function add(Member $member): void;

    public function search(string $query, int $page, int $size): MemberPage;

    /** Cuenta líneas sin devolver (incluidas vencidas), no cabeceras de préstamos. */
    public function countUnreturnedBooks(string $memberId): int;
}
