<?php

declare(strict_types=1);

namespace App\Library\Domain;

use App\Library\Domain\Errors\ValidationError;

final readonly class Quantity
{
    public function __construct(public int $value)
    {
        if ($value < 1 || $value > 2147483647) {
            throw new ValidationError('total_copies', 'El total debe ser positivo y representable en la base de datos.');
        }
    }
}
