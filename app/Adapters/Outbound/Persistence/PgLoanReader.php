<?php

declare(strict_types=1);

namespace App\Adapters\Outbound\Persistence;

use App\Application\Dto\LoanFilter;
use App\Application\Dto\OverdueCandidate;
use App\Application\Dto\Page;
use App\Application\Dto\PageRequest;
use App\Application\Ports\Outbound\LoanReader;
use App\Domain\LoanStatus;
use DateTimeImmutable;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

final class PgLoanReader implements LoanReader
{
    private const UNRETURNED = 'SELECT 1 FROM library.loan_item li WHERE li.loan_id = library.loan.id AND li.returned_at IS NULL';

    public function search(LoanFilter $filter, PageRequest $request, DateTimeImmutable $now): Page
    {
        $query = DB::table('library.loan');
        if ($filter->from !== null) {
            $query->where('loaned_at', '>=', PgTime::toDb(PgTime::startOfDay($filter->from)));
        }
        if ($filter->to !== null) {
            $query->where('loaned_at', '<', PgTime::toDb(PgTime::startOfNextDay($filter->to)));
        }
        if ($filter->memberId !== null) {
            $query->where('member_id', $filter->memberId);
        }
        if ($filter->status !== null) {
            $this->applyStatus($query, $filter->status, $now);
        }

        $total = (clone $query)->count();
        $rows = $query->orderByDesc('loaned_at')->orderBy('id')
            ->offset($request->offset())->limit($request->size)->get()->all();

        return Page::of(LoanHydrator::loans($rows), $request, $total);
    }

    public function findUnreturnedItemsOfMember(string $memberId): array
    {
        return DB::table('library.loan_item as li')
            ->join('library.loan as l', 'l.id', '=', 'li.loan_id')
            ->where('l.member_id', $memberId)
            ->whereNull('li.returned_at')
            ->select('li.*')
            ->orderBy('li.due_at')->orderBy('li.id')
            ->get()
            ->map(static fn (object $row) => LoanHydrator::item($row))
            ->all();
    }

    public function findOverdueCandidates(DateTimeImmutable $now): array
    {
        return DB::table('library.loan_item as li')
            ->join('library.loan as l', 'l.id', '=', 'li.loan_id')
            ->join('library.member as m', 'm.id', '=', 'l.member_id')
            ->whereNull('li.returned_at')
            ->where('li.due_at', '<', PgTime::toDb($now))
            ->select('li.*', 'm.full_name as member_name')
            ->get()
            ->map(static fn (object $row) => new OverdueCandidate($row->loan_id, $row->member_name, LoanHydrator::item($row)))
            ->all();
    }

    public function hasOpenLoansForBook(string $bookId): bool
    {
        return DB::table('library.loan_item')->where('book_id', $bookId)->whereNull('returned_at')->exists();
    }

    /**
     * Status is never stored: it is derived from the lines (closed: none unreturned; overdue: one unreturned past due).
     */
    private function applyStatus(Builder $query, LoanStatus $status, DateTimeImmutable $now): void
    {
        $overdue = self::UNRETURNED . ' AND li.due_at < ?';
        $nowValue = PgTime::toDb($now);
        match ($status) {
            LoanStatus::Closed => $query->whereRaw('NOT EXISTS (' . self::UNRETURNED . ')'),
            LoanStatus::Overdue => $query->whereRaw('EXISTS (' . $overdue . ')', [$nowValue]),
            LoanStatus::Open => $query->whereRaw('EXISTS (' . self::UNRETURNED . ')')
                ->whereRaw('NOT EXISTS (' . $overdue . ')', [$nowValue]),
        };
    }
}
