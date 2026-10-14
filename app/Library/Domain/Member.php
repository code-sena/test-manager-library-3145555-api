<?php

declare(strict_types=1);

namespace App\Library\Domain;

final readonly class Member
{
    public string $id;
    public string $fullName;

    public function __construct(string $id, string $fullName, public DocumentNumber $documentNumber)
    {
        $this->id = Guard::uuid($id);
        $this->fullName = Guard::text('full_name', $fullName, 200);
    }
}
