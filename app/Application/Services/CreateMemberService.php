<?php

declare(strict_types=1);

namespace App\Application\Services;

use App\Application\Errors\DocumentTaken;
use App\Application\Ports\Outbound\MemberRepository;
use App\Domain\DocumentNumber;
use App\Domain\Member;

final class CreateMemberService
{
    public function __construct(private readonly MemberRepository $members)
    {
    }

    public function execute(string $fullName, string $documentNumber): Member
    {
        $document = new DocumentNumber($documentNumber);
        $member = Member::create($fullName, $document);
        if ($this->members->isDocumentTaken($document)) {
            throw new DocumentTaken();
        }
        $this->members->save($member);

        return $member;
    }
}
