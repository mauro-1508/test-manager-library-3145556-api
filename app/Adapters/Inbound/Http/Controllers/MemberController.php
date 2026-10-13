<?php

declare(strict_types=1);

namespace App\Adapters\Inbound\Http\Controllers;

use App\Adapters\Inbound\Http\Presenters\MemberPresenter;
use App\Adapters\Inbound\Http\Presenters\PagePresenter;
use App\Adapters\Inbound\Http\Requests\CreateMemberRequest;
use App\Adapters\Inbound\Http\Requests\SearchMembersRequest;
use App\Application\Services\CreateMemberService;
use App\Application\Services\GetMemberService;
use App\Application\Services\SearchMembersService;
use Illuminate\Http\JsonResponse;

final class MemberController
{
    public function index(SearchMembersRequest $request, SearchMembersService $service): JsonResponse
    {
        $page = $service->execute($request->validated('q'), $request->pageRequest());

        return response()->json(PagePresenter::present($page, MemberPresenter::present(...)));
    }

    public function show(string $id, GetMemberService $service): JsonResponse
    {
        return response()->json(MemberPresenter::presentDetail($service->execute($id)));
    }

    public function store(CreateMemberRequest $request, CreateMemberService $service): JsonResponse
    {
        $member = $service->execute($request->validated('full_name'), $request->validated('document_number'));

        return response()->json(MemberPresenter::present($member), 201);
    }
}
