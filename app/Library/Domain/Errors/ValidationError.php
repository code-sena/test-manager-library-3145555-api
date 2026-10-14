<?php

declare(strict_types=1);

namespace App\Library\Domain\Errors;

use DomainException;

class ValidationError extends DomainException
{
    public function __construct(public readonly string $field, string $message)
    {
        parent::__construct($message);
    }
}
