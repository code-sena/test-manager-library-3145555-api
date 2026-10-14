<?php

declare(strict_types=1);

namespace App\Library\Application;

use App\Library\Domain\Member;
use InvalidArgumentException;

final readonly class MemberDetails
{
    public function __construct(public Member $member, public int $activeLoans)
    {
        if ($activeLoans < 0) {
            throw new InvalidArgumentException('El conteo sin devolver no puede ser negativo.');
        }
    }
}
