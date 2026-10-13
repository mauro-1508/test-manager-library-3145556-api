<?php

declare(strict_types=1);

namespace App\Adapters\Inbound\Http\Controllers;

use App\Adapters\Inbound\Http\Presenters\BookPresenter;
use App\Adapters\Inbound\Http\Requests\UploadCoverRequest;
use App\Application\Services\RemoveCoverService;
use App\Application\Services\UploadCoverService;
use Illuminate\Http\JsonResponse;

final class BookCoverController
{
    public function update(string $id, UploadCoverRequest $request, UploadCoverService $service): JsonResponse
    {
        $bytes = $request->bytes();

        return response()->json(BookPresenter::present($service->execute($id, $bytes, $request->detectedMimeType($bytes))));
    }

    public function destroy(string $id, RemoveCoverService $service): JsonResponse
    {
        return response()->json(BookPresenter::present($service->execute($id)));
    }
}
