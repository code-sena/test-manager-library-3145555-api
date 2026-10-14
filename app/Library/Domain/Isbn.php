<?php

declare(strict_types=1);

namespace App\Library\Domain;

use App\Library\Domain\Errors\ValidationError;

final readonly class Isbn
{
    public function __construct(public string $value)
    {
        if (preg_match('/\A[0-9]{13}\z/', $value) !== 1) {
            throw new ValidationError('isbn', 'El ISBN debe contener exactamente 13 dígitos.');
        }
    }
}
