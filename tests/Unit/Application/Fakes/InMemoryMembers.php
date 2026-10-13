<?php

declare(strict_types=1);

namespace Tests\Unit\Application\Fakes;

use App\Application\Dto\Page;
use App\Application\Dto\PageRequest;
use App\Application\Ports\Outbound\MemberRepository;
use App\Domain\DocumentNumber;
use App\Domain\Member;

final class InMemoryMembers implements MemberRepository
{
    /** @var array<string, Member> */
    private array $byId = [];

    public function save(Member $member): void
    {
        $this->byId[$member->id] = $member;
    }

    public function find(string $id): ?Member
    {
        return $this->byId[$id] ?? null;
    }

    public function isDocumentTaken(DocumentNumber $document): bool
    {
        foreach ($this->byId as $member) {
            if ($member->document->value === $document->value) {
                return true;
            }
        }

        return false;
    }

    public function search(?string $text, PageRequest $request): Page
    {
        $matches = array_filter(
            $this->byId,
            static fn (Member $member): bool => $text === null
                || stripos($member->fullName, $text) !== false
                || stripos($member->document->value, $text) !== false,
        );
        usort($matches, static fn (Member $a, Member $b): int => strcasecmp($a->fullName, $b->fullName));

        return Paginator::slice(array_values($matches), $request);
    }
}
