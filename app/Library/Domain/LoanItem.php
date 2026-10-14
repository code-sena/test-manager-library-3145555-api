<?php

declare(strict_types=1);

namespace App\Library\Domain;

use App\Library\Domain\Errors\ItemAlreadyReturned;
use DateTimeImmutable;

final readonly class LoanItem
{
    public string $id;
    public string $loanId;
    public string $bookId;
    public string $bookTitle;
    public string $categoryName;
    public DateTimeImmutable $dueAt;
    public ?DateTimeImmutable $returnedAt;

    public function __construct(
        string $id,
        string $loanId,
        string $bookId,
        string $bookTitle,
        public Isbn $isbn,
        string $categoryName,
        DateTimeImmutable $dueAt,
        ?DateTimeImmutable $returnedAt = null,
    ) {
        $this->id = Guard::uuid($id);
        $this->loanId = Guard::uuid($loanId, 'loan_id');
        $this->bookId = Guard::uuid($bookId, 'book_id');
        $this->bookTitle = Guard::text('book_title', $bookTitle, 250);
        $this->categoryName = Guard::text('category_name', $categoryName, 120);
        $this->dueAt = Guard::utc($dueAt);
        $this->returnedAt = $returnedAt === null ? null : Guard::utc($returnedAt);
    }

    public function isOverdue(DateTimeImmutable $now): bool
    {
        return $this->returnedAt === null && $this->dueAt < $now;
    }

    public function daysLate(DateTimeImmutable $now): int
    {
        return $this->isOverdue($now) ? (int) $this->dueAt->diff(Guard::utc($now))->days : 0;
    }

    public function returned(DateTimeImmutable $at): self
    {
        if ($this->returnedAt !== null) {
            throw new ItemAlreadyReturned('La línea ya fue devuelta.');
        }

        return new self($this->id, $this->loanId, $this->bookId, $this->bookTitle,
            $this->isbn, $this->categoryName, $this->dueAt, $at);
    }
}
