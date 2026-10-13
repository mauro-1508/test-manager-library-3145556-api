<?php

declare(strict_types=1);

namespace App\Application\Ports\Outbound;

use App\Application\Dto\Page;
use App\Application\Dto\PageRequest;
use App\Domain\DocumentNumber;
use App\Domain\Member;

interface MemberRepository
{
    public function save(Member $member): void;

    public function find(string $id): ?Member;

    public function isDocumentTaken(DocumentNumber $document): bool;

    /**
     * Members whose name or document contains $text, ordered by name.
     *
     * @return Page of Member
     */
    public function search(?string $text, PageRequest $request): Page;
}
