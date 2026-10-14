<?php

declare(strict_types=1);

namespace App\Library\Application\Services;

use App\Library\Application\MemberDetails;
use App\Library\Application\Ports\MemberRepository;
use App\Library\Domain\Errors\ResourceNotFound;
use App\Library\Domain\Guard;

final readonly class GetMemberService
{
    public function __construct(private MemberRepository $members)
    {
    }

    public function execute(string $id): MemberDetails
    {
        $id = Guard::uuid($id, 'member_id');
        $member = $this->members->find($id);
        if ($member === null) {
            throw new ResourceNotFound('No se encontró el socio.');
        }

        return new MemberDetails($member, $this->members->countUnreturnedBooks($id));
    }
}
