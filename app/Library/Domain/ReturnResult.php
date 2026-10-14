<?php

declare(strict_types=1);

namespace App\Library\Domain;

final readonly class ReturnResult
{
    public function __construct(public Loan $loan, public LoanItem $item, public Book $book)
    {
    }
}
