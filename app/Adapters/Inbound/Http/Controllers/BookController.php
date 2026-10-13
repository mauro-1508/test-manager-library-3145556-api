<?php

declare(strict_types=1);

namespace App\Adapters\Inbound\Http\Controllers;

use App\Adapters\Inbound\Http\Presenters\BookPresenter;
use App\Adapters\Inbound\Http\Presenters\PagePresenter;
use App\Adapters\Inbound\Http\Requests\CreateBookRequest;
use App\Adapters\Inbound\Http\Requests\SearchBooksRequest;
use App\Adapters\Inbound\Http\Requests\UpdateBookRequest;
use App\Application\Services\CreateBookService;
use App\Application\Services\DeleteBookService;
use App\Application\Services\GetBookService;
use App\Application\Services\SearchBooksService;
use App\Application\Services\UpdateBookService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;

final class BookController
{
    public function index(SearchBooksRequest $request, SearchBooksService $service): JsonResponse
    {
        $page = $service->execute($request->validated('q'), $request->validated('category_id'), $request->pageRequest());

        return response()->json(PagePresenter::present($page, BookPresenter::present(...)));
    }

    public function show(string $id, GetBookService $service): JsonResponse
    {
        return response()->json(BookPresenter::present($service->execute($id)));
    }

    public function store(CreateBookRequest $request, CreateBookService $service): JsonResponse
    {
        $view = $service->execute(
            $request->validated('title'),
            $request->validated('author'),
            $request->validated('isbn'),
            $request->validated('category_id'),
            $request->integer('total_copies'),
        );

        return response()->json(BookPresenter::present($view), 201);
    }

    public function update(string $id, UpdateBookRequest $request, UpdateBookService $service): JsonResponse
    {
        $view = $service->execute(
            $id,
            $request->validated('title'),
            $request->validated('author'),
            $request->validated('category_id'),
            $request->integer('total_copies'),
        );

        return response()->json(BookPresenter::present($view));
    }

    public function destroy(string $id, DeleteBookService $service): Response
    {
        $service->execute($id);

        return response()->noContent();
    }
}
