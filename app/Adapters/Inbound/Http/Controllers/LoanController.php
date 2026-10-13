<?php

declare(strict_types=1);

namespace App\Adapters\Inbound\Http\Controllers;

use App\Adapters\Inbound\Http\Middleware\AuthenticateJwt;
use App\Adapters\Inbound\Http\Presenters\LoanPresenter;
use App\Adapters\Inbound\Http\Presenters\PagePresenter;
use App\Adapters\Inbound\Http\Requests\LendBooksRequest;
use App\Adapters\Inbound\Http\Requests\ListLoansRequest;
use App\Application\Services\GetLoanService;
use App\Application\Services\LendBooksService;
use App\Application\Services\ListLoansService;
use App\Application\Services\OverdueService;
use App\Application\Services\ReturnItemService;
use Illuminate\Http\JsonResponse;

final class LoanController
{
    public function store(LendBooksRequest $request, LendBooksService $service): JsonResponse
    {
        $view = $service->execute(
            $request->validated('member_id'),
            $request->bookIds(),
            AuthenticateJwt::claimsOf($request)->userId,
        );

        return response()->json(LoanPresenter::present($view), 201);
    }

    public function index(ListLoansRequest $request, ListLoansService $service): JsonResponse
    {
        $page = $service->execute($request->loanFilter(), $request->pageRequest());

        return response()->json(PagePresenter::present($page, LoanPresenter::present(...)));
    }

    public function show(string $id, GetLoanService $service): JsonResponse
    {
        return response()->json(LoanPresenter::present($service->execute($id)));
    }

    public function overdue(OverdueService $service): JsonResponse
    {
        return response()->json(['items' => array_map(LoanPresenter::overdueLine(...), $service->execute())]);
    }

    public function returnItem(string $loanId, string $itemId, ReturnItemService $service): JsonResponse
    {
        return response()->json(LoanPresenter::item($service->execute($loanId, $itemId)));
    }
}
