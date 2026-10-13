<?php

declare(strict_types=1);

namespace App\Adapters\Inbound\Http\Controllers;

use App\Adapters\Inbound\Http\Requests\DayParameter;
use App\Adapters\Inbound\Http\Requests\MostBorrowedReportRequest;
use App\Application\Dto\MostBorrowedRow;
use App\Application\Services\MostBorrowedReportService;
use Illuminate\Http\JsonResponse;

final class ReportController
{
    public function mostBorrowed(MostBorrowedReportRequest $request, MostBorrowedReportService $service): JsonResponse
    {
        $rows = $service->execute($request->from(), $request->to());

        return response()->json([
            'from' => $request->from()->format(DayParameter::FORMAT),
            'to' => $request->to()->format(DayParameter::FORMAT),
            'rows' => array_map(
                static fn (MostBorrowedRow $row): array => [
                    'book_id' => $row->bookId,
                    'book_title' => $row->bookTitle,
                    'category_name' => $row->categoryName,
                    'times_borrowed' => $row->timesBorrowed,
                ],
                $rows,
            ),
        ]);
    }
}
