<?php

declare(strict_types=1);

namespace App\Library\Domain\Errors;

use DomainException;

final class MemberLimitExceeded extends DomainException
{
    public function __construct(public readonly int $currentCount)
    {
        parent::__construct('El socio tiene actualmente '.$currentCount.' libros sin devolver; el máximo es 3.');
    }
}
