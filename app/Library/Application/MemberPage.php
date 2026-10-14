<?php

declare(strict_types=1);

namespace App\Library\Application;

use App\Library\Domain\Member;
use InvalidArgumentException;

final readonly class MemberPage
{
    /** @param list<Member> $items */
    public function __construct(public array $items, public int $page, public int $size, public int $total)
    {
        if (!array_is_list($items) || $page < 1 || $size < 1 || $size > 50 || $total < count($items) || count($items) > $size) {
            throw new InvalidArgumentException('El resultado paginado no cumple el puerto de socios.');
        }
        foreach ($items as $item) {
            if (!$item instanceof Member) {
                throw new InvalidArgumentException('El resultado debe contener socios del dominio.');
            }
        }
    }
}
