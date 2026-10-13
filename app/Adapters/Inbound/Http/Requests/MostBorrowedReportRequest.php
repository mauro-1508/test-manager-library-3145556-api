<?php

declare(strict_types=1);

namespace App\Adapters\Inbound\Http\Requests;

use DateTimeImmutable;

final class MostBorrowedReportRequest extends ApiRequest
{
    /**
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return [
            'from' => ['required', 'date_format:' . DayParameter::FORMAT],
            'to' => ['required', 'date_format:' . DayParameter::FORMAT],
        ];
    }

    public function from(): DateTimeImmutable
    {
        return DayParameter::read($this, 'from');
    }

    public function to(): DateTimeImmutable
    {
        return DayParameter::read($this, 'to');
    }
}
