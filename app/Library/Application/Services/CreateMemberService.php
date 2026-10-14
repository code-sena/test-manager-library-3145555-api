<?php

declare(strict_types=1);

namespace App\Library\Application\Services;

use App\Library\Application\Ports\MemberRepository;
use App\Library\Domain\DocumentNumber;
use App\Library\Domain\Errors\DocumentTaken;
use App\Library\Domain\Member;

final readonly class CreateMemberService
{
    public function __construct(private MemberRepository $members)
    {
    }

    /** $id se asigna en el servidor; no es un campo de entrada HTTP. */
    public function execute(string $id, string $fullName, string $documentNumber): Member
    {
        $member = new Member($id, $fullName, new DocumentNumber($documentNumber));
        if ($this->members->findByDocument($member->documentNumber) !== null) {
            throw new DocumentTaken('El documento ya está registrado.');
        }
        $this->members->add($member);

        return $member;
    }
}
