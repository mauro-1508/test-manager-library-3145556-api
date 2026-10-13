<?php

declare(strict_types=1);

namespace App\Application\Services;

use App\Application\Dto\LoanFilter;
use App\Application\Dto\LoanView;
use App\Application\Dto\Page;
use App\Application\Dto\PageRequest;
use App\Application\Ports\Outbound\Clock;
use App\Application\Ports\Outbound\LoanReader;
use App\Domain\Loan;

final class ListLoansService
{
    public function __construct(
        private readonly LoanReader $loans,
        private readonly Clock $clock,
    ) {
    }

    /**
     * @return Page of LoanView, newest loan first
     */
    public function execute(LoanFilter $filter, PageRequest $request): Page
    {
        $now = $this->clock->now();

        return $this->loans->search($filter, $request, $now)
            ->map(static fn (Loan $loan): LoanView => LoanView::of($loan, $now));
    }
}
