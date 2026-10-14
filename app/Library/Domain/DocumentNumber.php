<?php

declare(strict_types=1);

namespace App\Library\Domain;

final readonly class DocumentNumber
{
    public string $value;

    public function __construct(string $value)
    {
        $this->value = Guard::text('document_number', $value, 30);
    }
}
