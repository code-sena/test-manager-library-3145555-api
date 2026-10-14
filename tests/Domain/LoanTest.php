<?php

declare(strict_types=1);

namespace Tests\Domain;

use App\Library\Domain\Category;
use App\Library\Domain\Errors\BookNotAvailable;
use App\Library\Domain\Errors\DuplicateBookInLoan;
use App\Library\Domain\Errors\ItemAlreadyReturned;
use App\Library\Domain\Errors\MemberHasOverdue;
use App\Library\Domain\Errors\MemberLimitExceeded;
use App\Library\Domain\Errors\ResourceNotFound;
use App\Library\Domain\Errors\ValidationError;
use App\Library\Domain\Loan;
use App\Library\Domain\LoanItem;
use App\Library\Domain\Quantity;
use DateTimeImmutable;
use LogicException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Tests\Fakes\DomainFixtures as F;
use Tests\Fakes\FakeClock;

final class LoanTest extends TestCase
{
    public function test_lending_freezes_catalog_values_and_decrements_each_book_once(): void
    {
        $at = new DateTimeImmutable('2026-10-14T07:00:00-05:00');
        $books = [F::book(1), F::book(2)];
        $result = F::lend($books, $at);
        self::assertCount(2, $result->loan->items);
        self::assertSame([1, 1], array_map(fn ($book) => $book->availableCopies, $result->books));
        self::assertSame([2, 2], array_map(fn ($book) => $book->availableCopies, $books));
        self::assertSame('2026-10-28T12:00:00+00:00', $result->loan->items[0]->dueAt->format('c'));
        self::assertSame('UTC', $result->loan->loanedAt->getTimezone()->getName());
        $newCategory = new Category('22222222-2222-4222-8222-222222222222', 'Ciencia');
        $edited = $result->books[0]->revise('Nuevo título', 'Nuevo autor', $newCategory, new Quantity(2));
        self::assertSame('Ciencia', $edited->category->name);
        self::assertSame('Título 1', $result->loan->items[0]->bookTitle);
        self::assertSame('Novela', $result->loan->items[0]->categoryName);
        self::assertSame($books[0]->isbn->value, $result->loan->items[0]->isbn->value);
    }

    #[DataProvider('invalidLineCounts')]
    public function test_loan_cardinality_is_one_to_three(int $count): void
    {
        $books = [];
        for ($index = 1; $index <= $count; $index++) {
            $books[] = F::book($index);
        }
        $this->expectException(ValidationError::class);
        F::lend($books);
    }

    public static function invalidLineCounts(): array
    {
        return [[0], [4]];
    }

    public function test_duplicate_books_are_rejected(): void
    {
        $this->expectException(DuplicateBookInLoan::class);
        F::lend([F::book(1), F::book(1)]);
    }

    public function test_failed_lending_does_not_partially_change_any_input_book(): void
    {
        $first = F::book(1, 1);
        $second = F::book(2, 1)->withdraw();
        try {
            F::lend([$first, $second]);
            self::fail('Debió rechazar el libro sin ejemplares.');
        } catch (BookNotAvailable) {
            self::assertSame(1, $first->availableCopies);
            self::assertSame(0, $second->availableCopies);
        }
    }

    public function test_three_unreturned_books_are_counted_across_separate_loans(): void
    {
        $first = F::lend([F::book(1), F::book(2)]);
        $second = F::lend([F::book(3)], history: [$first->loan], number: 20);
        try {
            F::lend([F::book(4)], history: [$first->loan, $second->loan], number: 30);
            self::fail('Debió rechazar el cuarto libro.');
        } catch (MemberLimitExceeded $exception) {
            self::assertSame(3, $exception->currentCount);
            self::assertStringContainsString('3', $exception->getMessage());
        }
    }

    public function test_returned_lines_do_not_consume_the_member_limit(): void
    {
        $first = F::lend([F::book(1), F::book(2), F::book(3)]);
        $returned = $first->loan->returnItem($first->loan->items[0]->id, $first->books[0],
            new DateTimeImmutable('2026-10-14T13:00:00Z'));
        $next = F::lend([F::book(4)], history: [$returned->loan], number: 20);
        self::assertCount(1, $next->loan->items);
    }

    public function test_overdue_member_cannot_borrow_even_when_the_new_request_fits_the_limit(): void
    {
        $old = F::lend([F::book(1)], new DateTimeImmutable('2026-09-01T12:00:00Z'));
        $this->expectException(MemberHasOverdue::class);
        F::lend([F::book(2)], history: [$old->loan]);
    }

    public function test_status_is_computed_with_fake_time_and_the_due_boundary_is_strict(): void
    {
        $clock = new FakeClock(new DateTimeImmutable('2026-10-14T12:00:00Z'));
        $result = F::lend([F::book(1), F::book(2)], $clock->now());
        self::assertSame('open', $result->loan->status($clock->now()));
        $clock->set(new DateTimeImmutable('2026-10-28T12:00:00Z'));
        self::assertSame('open', $result->loan->status($clock->now()));
        $clock->set(new DateTimeImmutable('2026-10-28T12:00:00.000001Z'));
        self::assertSame('overdue', $result->loan->status($clock->now()));
        self::assertSame(0, $result->loan->items[0]->daysLate($clock->now()));
        $clock->set(new DateTimeImmutable('2026-10-29T11:59:59.999999Z'));
        self::assertSame(0, $result->loan->items[0]->daysLate($clock->now()));
        $clock->set(new DateTimeImmutable('2026-10-29T12:00:00Z'));
        self::assertSame(1, $result->loan->items[0]->daysLate($clock->now()));
        $first = $result->loan->returnItem($result->loan->items[0]->id, $result->books[0], $clock->now());
        self::assertSame('overdue', $first->loan->status($clock->now()));
        $last = $first->loan->returnItem($result->loan->items[1]->id, $result->books[1], $clock->now());
        self::assertSame('closed', $last->loan->status($clock->now()));
        $clock->set(new DateTimeImmutable('2027-01-01T00:00:00Z'));
        self::assertSame('closed', $last->loan->status($clock->now()));
        self::assertSame(0, $last->item->daysLate($clock->now()));
    }

    public function test_return_is_per_line_and_cannot_restock_twice(): void
    {
        $result = F::lend([F::book(1, 1)]);
        $returned = $result->loan->returnItem($result->loan->items[0]->id, $result->books[0],
            new DateTimeImmutable('2026-10-14T13:00:00Z'));
        self::assertSame(1, $returned->book->availableCopies);
        self::assertSame('closed', $returned->loan->status(new DateTimeImmutable('2026-10-14T14:00:00Z')));
        self::assertNull($result->loan->items[0]->returnedAt);
        $this->expectException(ItemAlreadyReturned::class);
        $returned->loan->returnItem($returned->item->id, $returned->book, new DateTimeImmutable('2026-10-14T14:00:00Z'));
    }

    public function test_unknown_line_is_rejected_without_touching_inventory(): void
    {
        $result = F::lend([F::book(1)]);
        $this->expectException(ResourceNotFound::class);
        $result->loan->returnItem(F::id(9999), $result->books[0], new DateTimeImmutable('2026-10-14T13:00:00Z'));
    }

    public function test_wrong_book_and_backdated_return_are_rejected(): void
    {
        $result = F::lend([F::book(1)]);
        try {
            $result->loan->returnItem($result->loan->items[0]->id, F::book(2)->withdraw(),
                new DateTimeImmutable('2026-10-14T13:00:00Z'));
            self::fail('Debió rechazar el libro incorrecto.');
        } catch (ValidationError) {
            self::assertSame(1, $result->books[0]->availableCopies);
        }
        $this->expectException(ValidationError::class);
        $result->loan->returnItem($result->loan->items[0]->id, $result->books[0],
            new DateTimeImmutable('2026-10-13T12:00:00Z'));
    }

    public function test_restoration_rejects_a_line_with_a_different_due_period(): void
    {
        $item = new LoanItem(F::id(1000), F::id(10), F::id(1), 'Título', F::book()->isbn, 'Novela',
            new DateTimeImmutable('2026-10-29T12:00:00Z'));
        $this->expectException(ValidationError::class);
        Loan::restore(F::id(10), F::member()->id, F::id(200), new DateTimeImmutable('2026-10-14T12:00:00Z'), [$item]);
    }

    public function test_member_history_must_belong_to_the_requested_member(): void
    {
        $result = F::lend([F::book(1)]);
        $foreign = Loan::restore($result->loan->id, F::id(300), F::id(200), $result->loan->loanedAt, $result->loan->items);
        $this->expectException(LogicException::class);
        F::lend([F::book(2)], history: [$foreign]);
    }
}
