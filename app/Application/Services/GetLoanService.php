<?php

declare(strict_types=1);

namespace App\Application\Services;

use App\Application\Dto\LoanView;
use App\Application\Errors\NotFound;
use App\Application\Ports\Outbound\Clock;
use App\Application\Ports\Outbound\LoanRepository;

final class GetLoanService
{
    public function __construct(
        private readonly LoanRepository $loans,
        private readonly Clock $clock,
    ) {
    }

    public function execute(string $loanId): LoanView
    {
        $loan = $this->loans->find($loanId) ?? throw NotFound::loan();

        return LoanView::of($loan, $this->clock->now());
    }
}
