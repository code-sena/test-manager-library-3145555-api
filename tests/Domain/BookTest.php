<?php

declare(strict_types=1);

namespace Tests\Domain;

use App\Library\Domain\Book;
use App\Library\Domain\Errors\BookHasOpenLoans;
use App\Library\Domain\Errors\BookNotAvailable;
use App\Library\Domain\Errors\ValidationError;
use App\Library\Domain\Quantity;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use Tests\Fakes\DomainFixtures as F;

final class BookTest extends TestCase
{
    public function test_create_withdraw_and_restock_keep_inventory_in_range_without_mutating_originals(): void
    {
        $book = F::book(total: 1);
        self::assertSame(1, $book->availableCopies);
        $withdrawn = $book->withdraw();
        self::assertSame(0, $withdrawn->availableCopies);
        self::assertSame(1, $book->availableCopies);
        self::assertSame(1, $withdrawn->restock()->availableCopies);
        $this->expectException(BookNotAvailable::class);
        $withdrawn->withdraw();
    }

    public function test_restock_cannot_exceed_the_total(): void
    {
        $this->expectException(ValidationError::class);
        F::book()->restock();
    }

    public function test_total_changes_preserve_the_number_of_borrowed_copies_and_the_isbn(): void
    {
        $book = F::book(total: 4)->withdraw()->withdraw();
        $expanded = $book->revise(' Nuevo título ', ' Otro autor ', F::category(), new Quantity(6));
        self::assertSame(4, $expanded->availableCopies);
        self::assertSame('Nuevo título', $expanded->title);
        self::assertSame($book->isbn, $expanded->isbn);
        self::assertSame(0, $book->revise('Título', 'Autor', F::category(), new Quantity(2))->availableCopies);
        self::assertSame(2, $book->availableCopies);
        $this->expectException(ValidationError::class);
        $book->revise('Título', 'Autor', F::category(), new Quantity(1));
    }

    public function test_soft_delete_preserves_data_and_prevents_new_loans(): void
    {
        $book = F::book()->deactivate(new DateTimeImmutable('2026-10-14T07:00:00-05:00'), false);
        self::assertSame('UTC', $book->deletedAt->getTimezone()->getName());
        self::assertSame('Título 1', $book->title);
        $this->expectException(BookNotAvailable::class);
        $book->withdraw();
    }

    public function test_open_loans_prevent_soft_delete_even_if_inventory_was_incorrectly_reported_as_full(): void
    {
        $this->expectException(BookHasOpenLoans::class);
        F::book()->deactivate(new DateTimeImmutable('2026-10-14T12:00:00Z'), true);
    }

    public function test_outstanding_inventory_prevents_soft_delete(): void
    {
        $this->expectException(BookHasOpenLoans::class);
        F::book()->withdraw()->deactivate(new DateTimeImmutable('2026-10-14T12:00:00Z'), false);
    }

    public function test_cover_can_be_set_or_removed_but_never_empty(): void
    {
        $book = F::book()->withCover('opaque-key');
        self::assertSame('opaque-key', $book->coverKey);
        self::assertNull($book->withCover(null)->coverKey);
        $this->expectException(ValidationError::class);
        $book->withCover('');
    }

    public function test_required_text_is_trimmed_and_unicode_column_limits_are_respected(): void
    {
        $book = F::book();
        $valid = new Book($book->id, ' '.str_repeat('á', 250).' ', ' Autor ', $book->isbn,
            $book->category, $book->totalCopies, 2);
        self::assertSame(250, mb_strlen($valid->title));
        self::assertSame('Autor', $valid->author);
        $this->expectException(ValidationError::class);
        $book->revise(str_repeat('á', 251), 'Autor', F::category(), new Quantity(2));
    }
}
