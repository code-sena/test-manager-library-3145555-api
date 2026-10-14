<?php

declare(strict_types=1);

namespace App\Library\Domain;

use App\Library\Domain\Errors\DuplicateBookInLoan;
use App\Library\Domain\Errors\ResourceNotFound;
use App\Library\Domain\Errors\ValidationError;
use DateInterval;
use DateTimeImmutable;

final readonly class Loan
{
    public string $id;
    public string $memberId;
    public string $loanedByUserId;
    public DateTimeImmutable $loanedAt;

    /** @param list<LoanItem> $items */
    private function __construct(string $id, string $memberId, string $operatorId, DateTimeImmutable $at, public array $items)
    {
        $this->id = Guard::uuid($id);
        $this->memberId = Guard::uuid($memberId, 'member_id');
        $this->loanedByUserId = Guard::uuid($operatorId, 'loaned_by_user_id');
        $this->loanedAt = Guard::utc($at);
        if (!array_is_list($items) || count($items) < 1 || count($items) > 3) {
            throw new ValidationError('items', 'El préstamo debe tener entre 1 y 3 líneas.');
        }
        $bookIds = [];
        $itemIds = [];
        foreach ($items as $item) {
            if (!$item instanceof LoanItem || $item->loanId !== $this->id) {
                throw new ValidationError('items', 'La línea debe pertenecer al préstamo.');
            }
            if (isset($bookIds[$item->bookId])) {
                throw new DuplicateBookInLoan('No se permite repetir un libro en el préstamo.');
            }
            if (isset($itemIds[$item->id]) || $item->dueAt != $this->loanedAt->add(new DateInterval('P14D'))) {
                throw new ValidationError('items', 'La línea debe tener identidad única y vencimiento a 14 días.');
            }
            if ($item->returnedAt !== null && $item->returnedAt < $this->loanedAt) {
                throw new ValidationError('returned_at', 'La devolución no puede ser anterior al préstamo.');
            }
            $bookIds[$item->bookId] = true;
            $itemIds[$item->id] = true;
        }
    }

    /** @param list<LoanItem> $items */
    public static function restore(string $id, string $memberId, string $operatorId, DateTimeImmutable $at, array $items): self
    {
        return new self($id, $memberId, $operatorId, $at, $items);
    }

    /** @param list<Book> $books @param list<string> $itemIds @param list<Loan> $memberLoans */
    public static function lend(string $id, Member $member, string $operatorId, DateTimeImmutable $at,
        array $books, array $itemIds, array $memberLoans): LendingResult
    {
        $at = Guard::utc($at);
        LendingPolicy::assertCanBorrow($member->id, $memberLoans, count($books), $at);
        if (!array_is_list($books) || !array_is_list($itemIds) || count($books) !== count($itemIds)) {
            throw new ValidationError('items', 'Cada libro debe tener una identidad de línea asignada por el servidor.');
        }
        $seen = [];
        foreach ($books as $book) {
            if (!$book instanceof Book) {
                throw new ValidationError('items', 'Cada línea debe contener un libro válido.');
            }
            if (isset($seen[$book->id])) {
                throw new DuplicateBookInLoan('No se permite repetir un libro en el préstamo.');
            }
            $seen[$book->id] = true;
        }
        $items = [];
        $updatedBooks = [];
        foreach ($books as $index => $book) {
            [$item, $updated] = self::addItem($id, $itemIds[$index], $book, $at);
            $items[] = $item;
            $updatedBooks[] = $updated;
        }

        return new LendingResult(new self($id, $member->id, $operatorId, $at, $items), $updatedBooks);
    }

    public function status(DateTimeImmutable $now): string
    {
        $open = false;
        foreach ($this->items as $item) {
            if ($item->isOverdue($now)) {
                return 'overdue';
            }
            $open = $open || $item->returnedAt === null;
        }

        return $open ? 'open' : 'closed';
    }

    public function returnItem(string $itemId, Book $book, DateTimeImmutable $at): ReturnResult
    {
        $itemId = Guard::uuid($itemId, 'item_id');
        foreach ($this->items as $index => $item) {
            if ($item->id !== $itemId) {
                continue;
            }
            if ($item->bookId !== $book->id) {
                throw new ValidationError('book_id', 'El libro no corresponde a la línea.');
            }
            $returned = $item->returned($at);
            $updatedBook = $book->restock();
            $items = $this->items;
            $items[$index] = $returned;

            return new ReturnResult(new self($this->id, $this->memberId, $this->loanedByUserId, $this->loanedAt, $items),
                $returned, $updatedBook);
        }

        throw new ResourceNotFound('No existe la línea en este préstamo.');
    }

    private static function addItem(string $loanId, string $itemId, Book $book, DateTimeImmutable $at): array
    {
        $updatedBook = $book->withdraw();
        $item = new LoanItem($itemId, $loanId, $book->id, $book->title, $book->isbn,
            $book->category->name, $at->add(new DateInterval('P14D')));

        return [$item, $updatedBook];
    }
}
