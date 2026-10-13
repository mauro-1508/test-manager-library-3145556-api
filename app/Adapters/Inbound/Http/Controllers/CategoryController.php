<?php

declare(strict_types=1);

namespace App\Adapters\Inbound\Http\Controllers;

use App\Adapters\Inbound\Http\Presenters\BookPresenter;
use App\Application\Services\ListCategoriesService;
use Illuminate\Http\JsonResponse;

final class CategoryController
{
    public function index(ListCategoriesService $service): JsonResponse
    {
        return response()->json(['items' => array_map(BookPresenter::category(...), $service->execute())]);
    }
}
