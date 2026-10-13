<?php

declare(strict_types=1);

namespace App\Application\Services;

use App\Application\Dto\Page;
use App\Application\Dto\PageRequest;
use App\Application\Ports\Outbound\MemberRepository;

final class SearchMembersService
{
    public function __construct(private readonly MemberRepository $members)
    {
    }

    /**
     * @return Page of Member
     */
    public function execute(?string $text, PageRequest $request): Page
    {
        return $this->members->search($text, $request);
    }
}
