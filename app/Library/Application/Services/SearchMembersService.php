<?php

declare(strict_types=1);

namespace App\Library\Application\Services;

use App\Library\Application\MemberPage;
use App\Library\Application\Ports\MemberRepository;
use App\Library\Domain\Errors\ValidationError;

final readonly class SearchMembersService
{
    public function __construct(private MemberRepository $members)
    {
    }

    public function execute(string $query = '', int $page = 1, int $size = 20): MemberPage
    {
        if ($page < 1 || $size < 1 || $size > 50) {
            throw new ValidationError('page/size', 'La paginación debe ser positiva y el tamaño no puede superar 50.');
        }

        return $this->members->search(trim($query), $page, $size);
    }
}
