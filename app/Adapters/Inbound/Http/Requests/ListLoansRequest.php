<?php

declare(strict_types=1);

namespace App\Adapters\Inbound\Http\Requests;

use App\Application\Dto\LoanFilter;
use App\Domain\LoanStatus;
use Illuminate\Validation\Rule;

final class ListLoansRequest extends PagedRequest
{
    protected function filterRules(): array
    {
        return [
            'from' => ['nullable', 'date_format:' . DayParameter::FORMAT],
            'to' => ['nullable', 'date_format:' . DayParameter::FORMAT],
            'status' => ['nullable', Rule::in(array_column(LoanStatus::cases(), 'value'))],
            'member_id' => ['nullable', 'uuid'],
        ];
    }

    public function loanFilter(): LoanFilter
    {
        return new LoanFilter(
            DayParameter::read($this, 'from'),
            DayParameter::read($this, 'to'),
            LoanStatus::tryFrom((string) $this->query('status')),
            $this->query('member_id'),
        );
    }
}
