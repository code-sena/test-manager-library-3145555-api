<?php

declare(strict_types=1);

namespace App\Library\Domain;

use App\Library\Domain\Errors\MemberHasOverdue;
use App\Library\Domain\Errors\MemberLimitExceeded;
use App\Library\Domain\Errors\ValidationError;
use DateTimeImmutable;
use LogicException;

final class LendingPolicy
{
    /** @param list<Loan> $loans Préstamos del socio obtenidos por la aplicación. */
    public static function assertCanBorrow(string $memberId, array $loans, int $requested, DateTimeImmutable $now): void
    {
        if ($requested < 1 || $requested > 3) {
            throw new ValidationError('items', 'El préstamo debe contener entre 1 y 3 libros.');
        }
        $count = 0;
        $overdue = false;
        foreach ($loans as $loan) {
            if (!$loan instanceof Loan || $loan->memberId !== $memberId) {
                throw new LogicException('La consulta de préstamos no corresponde al socio.');
            }
            foreach ($loan->items as $item) {
                if ($item->returnedAt === null) {
                    $count++;
                    $overdue = $overdue || $item->isOverdue($now);
                }
            }
        }
        if ($overdue) {
            throw new MemberHasOverdue('El socio tiene libros vencidos sin devolver.');
        }
        if ($count + $requested > 3) {
            throw new MemberLimitExceeded($count);
        }
    }
}
