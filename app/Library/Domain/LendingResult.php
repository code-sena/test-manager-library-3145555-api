<?php

declare(strict_types=1);

namespace App\Library\Domain;

final readonly class LendingResult
{
    /** @param list<Book> $books Inventario resultante, todavía sin persistir. */
    public function __construct(public Loan $loan, public array $books)
    {
    }
}
