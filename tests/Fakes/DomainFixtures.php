<?php

declare(strict_types=1);

namespace Tests\Fakes;

use App\Library\Domain\Book;
use App\Library\Domain\Category;
use App\Library\Domain\DocumentNumber;
use App\Library\Domain\Isbn;
use App\Library\Domain\Loan;
use App\Library\Domain\LendingResult;
use App\Library\Domain\Member;
use App\Library\Domain\Quantity;
use DateTimeImmutable;

final class DomainFixtures
{
    public static function id(int $number): string
    {
        return sprintf('%08x-0000-4000-8000-%012x', $number, $number);
    }

    public static function member(): Member
    {
        return new Member(self::id(100), 'Socio de prueba', new DocumentNumber('fixture-001'));
    }

    public static function category(): Category
    {
        return new Category('11111111-1111-4111-8111-111111111111', 'Novela');
    }

    public static function book(int $number = 1, int $total = 2): Book
    {
        return Book::create(self::id($number), 'Título '.$number, 'Autor de prueba',
            new Isbn(str_pad((string) $number, 13, '0', STR_PAD_LEFT)), self::category(), new Quantity($total));
    }

    public static function lend(array $books, ?DateTimeImmutable $at = null, array $history = [], int $number = 10): LendingResult
    {
        $ids = [];
        foreach ($books as $index => $book) {
            $ids[] = self::id($number * 10 + $index + 1000);
        }

        return Loan::lend(self::id($number), self::member(), self::id(200),
            $at ?? new DateTimeImmutable('2026-10-14T12:00:00Z'), $books, $ids, $history);
    }
}
