<?php

declare(strict_types=1);

namespace App\Library\Domain;

final readonly class Category
{
    public string $id;
    public string $name;

    public function __construct(string $id, string $name)
    {
        $this->id = Guard::uuid($id, 'category_id');
        $this->name = Guard::text('category_name', $name, 120);
    }
}
