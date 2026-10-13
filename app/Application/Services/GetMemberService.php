<?php

declare(strict_types=1);

namespace App\Application\Services;

use App\Application\Dto\MemberView;
use App\Application\Errors\NotFound;
use App\Application\Ports\Outbound\LoanReader;
use App\Application\Ports\Outbound\MemberRepository;

final class GetMemberService
{
    public function __construct(
        private readonly MemberRepository $members,
        private readonly LoanReader $loans,
    ) {
    }

    public function execute(string $memberId): MemberView
    {
        $member = $this->members->find($memberId) ?? throw NotFound::member();

        return new MemberView($member, count($this->loans->findUnreturnedItemsOfMember($memberId)));
    }
}
