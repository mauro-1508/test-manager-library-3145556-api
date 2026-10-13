<?php

declare(strict_types=1);

namespace App\Adapters\Outbound\Persistence;

use App\Application\Dto\Page;
use App\Application\Dto\PageRequest;
use App\Application\Errors\DocumentTaken;
use App\Application\Ports\Outbound\MemberRepository;
use App\Domain\DocumentNumber;
use App\Domain\Member;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

final class PgMemberRepository implements MemberRepository
{
    public function save(Member $member): void
    {
        try {
            DB::table('library.member')->upsert(
                [['id' => $member->id, 'full_name' => $member->fullName, 'document_number' => $member->document->value]],
                ['id'],
                ['full_name', 'document_number'],
            );
        } catch (QueryException $error) {
            throw PgErrors::isUniqueViolation($error) ? new DocumentTaken() : $error;
        }
    }

    public function find(string $id): ?Member
    {
        $row = DB::table('library.member')->where('id', $id)->first();

        return $row === null ? null : self::hydrate($row);
    }

    public function isDocumentTaken(DocumentNumber $document): bool
    {
        return DB::table('library.member')->where('document_number', $document->value)->exists();
    }

    public function search(?string $text, PageRequest $request): Page
    {
        $query = DB::table('library.member');
        if ($text !== null && trim($text) !== '') {
            $pattern = PgTime::containsPattern(trim($text));
            $query->whereRaw('(full_name ILIKE ? OR document_number ILIKE ?)', [$pattern, $pattern]);
        }

        $total = (clone $query)->count();
        $rows = $query->orderBy('full_name')->orderBy('id')
            ->offset($request->offset())->limit($request->size)->get();

        return Page::of($rows->map(static fn (object $row): Member => self::hydrate($row))->all(), $request, $total);
    }

    private static function hydrate(object $row): Member
    {
        return new Member($row->id, $row->full_name, new DocumentNumber($row->document_number));
    }
}
