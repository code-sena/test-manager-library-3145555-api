<?php

declare(strict_types=1);

namespace App\Library\Domain;

use App\Library\Domain\Errors\BookHasOpenLoans;
use App\Library\Domain\Errors\BookNotAvailable;
use App\Library\Domain\Errors\ValidationError;
use DateTimeImmutable;

final readonly class Book
{
    public string $id;
    public string $title;
    public string $author;
    public ?DateTimeImmutable $deletedAt;

    public function __construct(
        string $id,
        string $title,
        string $author,
        public Isbn $isbn,
        public Category $category,
        public Quantity $totalCopies,
        public int $availableCopies,
        public ?string $coverKey = null,
        ?DateTimeImmutable $deletedAt = null,
    ) {
        $this->id = Guard::uuid($id);
        $this->title = Guard::text('title', $title, 250);
        $this->author = Guard::text('author', $author, 200);
        if ($availableCopies < 0 || $availableCopies > $totalCopies->value) {
            throw new ValidationError('available_copies', 'Los disponibles deben estar entre cero y el total.');
        }
        if ($coverKey !== null && (trim($coverKey) === '' || mb_strlen($coverKey, 'UTF-8') > 512)) {
            throw new ValidationError('cover_key', 'La clave de portada debe ser nula o no vacía.');
        }
        $this->deletedAt = $deletedAt === null ? null : Guard::utc($deletedAt);
    }

    public static function create(string $id, string $title, string $author, Isbn $isbn, Category $category, Quantity $total): self
    {
        return new self($id, $title, $author, $isbn, $category, $total, $total->value);
    }

    public function withdraw(): self
    {
        if ($this->deletedAt !== null || $this->availableCopies === 0) {
            throw new BookNotAvailable('No quedan ejemplares disponibles del libro.');
        }

        return $this->copy(available: $this->availableCopies - 1);
    }

    public function restock(): self
    {
        if ($this->availableCopies === $this->totalCopies->value) {
            throw new ValidationError('available_copies', 'La reposición superaría el total de ejemplares.');
        }

        return $this->copy(available: $this->availableCopies + 1);
    }

    public function revise(string $title, string $author, Category $category, Quantity $total): self
    {
        if ($this->deletedAt !== null) {
            throw new ValidationError('id', 'El libro está dado de baja.');
        }
        $available = $this->availableCopies + ($total->value - $this->totalCopies->value);

        return new self($this->id, $title, $author, $this->isbn, $category, $total, $available, $this->coverKey);
    }

    public function withCover(?string $key): self
    {
        return new self($this->id, $this->title, $this->author, $this->isbn, $this->category,
            $this->totalCopies, $this->availableCopies, $key, $this->deletedAt);
    }

    public function deactivate(DateTimeImmutable $at, bool $hasOpenLoans): self
    {
        if ($hasOpenLoans || $this->availableCopies !== $this->totalCopies->value) {
            throw new BookHasOpenLoans('Un libro con préstamos abiertos no se puede dar de baja.');
        }

        return new self($this->id, $this->title, $this->author, $this->isbn, $this->category,
            $this->totalCopies, $this->availableCopies, $this->coverKey, $this->deletedAt ?? Guard::utc($at));
    }

    private function copy(int $available): self
    {
        return new self($this->id, $this->title, $this->author, $this->isbn, $this->category,
            $this->totalCopies, $available, $this->coverKey, $this->deletedAt);
    }
}
