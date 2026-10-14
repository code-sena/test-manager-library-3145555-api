<?php

declare(strict_types=1);

// Deliberately load only domain classes: no vendor/autoload.php or framework bootstrap.
spl_autoload_register(static function (string $class): void {
    $prefix = 'App\\Library\\Domain\\';
    if (str_starts_with($class, $prefix)) {
        require __DIR__.'/../../app/Library/Domain/'.str_replace('\\', '/', substr($class, strlen($prefix))).'.php';
    }
});

use App\Library\Domain\Book;
use App\Library\Domain\Category;
use App\Library\Domain\DocumentNumber;
use App\Library\Domain\Isbn;
use App\Library\Domain\Loan;
use App\Library\Domain\Member;
use App\Library\Domain\Quantity;
use App\Library\Domain\Errors\ItemAlreadyReturned;

$id = static fn (int $n): string => sprintf('%08x-0000-4000-8000-%012x', $n, $n);
$category = new Category($id(1), 'Novela');
$book = Book::create($id(2), 'Libro', 'Autor', new Isbn('1234567890123'), $category, new Quantity(1));
$member = new Member($id(3), 'Socio', new DocumentNumber('123'));
$at = new DateTimeImmutable('2026-10-14T12:00:00Z');
$lending = Loan::lend($id(4), $member, $id(5), $at, [$book], [$id(6)], []);
$return = $lending->loan->returnItem($id(6), $lending->books[0], $at->modify('+15 days'));
if ($book->availableCopies !== 1 || $lending->books[0]->availableCopies !== 0
    || $lending->loan->status($at->modify('+15 days')) !== 'overdue'
    || $return->loan->status($at->modify('+15 days')) !== 'closed' || $return->book->availableCopies !== 1) {
    throw new RuntimeException('Domain lifecycle failed.');
}
try {
    $return->loan->returnItem($id(6), $return->book, $at->modify('+16 days'));
    throw new RuntimeException('Repeated return was accepted.');
} catch (ItemAlreadyReturned) {
    echo "domain-ok\n";
}
