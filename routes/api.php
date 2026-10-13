<?php

use App\Adapters\Inbound\Http\Controllers\AuthController;
use App\Adapters\Inbound\Http\Controllers\BookController;
use App\Adapters\Inbound\Http\Controllers\BookCoverController;
use App\Adapters\Inbound\Http\Controllers\CategoryController;
use App\Adapters\Inbound\Http\Controllers\LoanController;
use App\Adapters\Inbound\Http\Controllers\MemberController;
use App\Adapters\Inbound\Http\Controllers\ReportController;
use App\Adapters\Inbound\Http\Controllers\UserController;
use App\Adapters\Inbound\Http\HealthController;
use App\Domain\Role;
use Illuminate\Support\Facades\Route;

$admin = 'role:' . Role::Admin->value;
$staff = 'role:' . Role::Admin->value . ',' . Role::Librarian->value;

Route::get('/health', HealthController::class);
Route::post('/auth/login', [AuthController::class, 'login']);

Route::middleware('jwt')->group(function () use ($admin, $staff): void {
    Route::get('/categories', [CategoryController::class, 'index']);

    Route::get('/books', [BookController::class, 'index']);
    Route::get('/books/{id}', [BookController::class, 'show'])->whereUuid('id');

    Route::middleware($admin)->group(function (): void {
        Route::post('/users', [UserController::class, 'store']);

        Route::post('/books', [BookController::class, 'store']);
        Route::put('/books/{id}', [BookController::class, 'update'])->whereUuid('id');
        Route::delete('/books/{id}', [BookController::class, 'destroy'])->whereUuid('id');
        Route::put('/books/{id}/cover', [BookCoverController::class, 'update'])->whereUuid('id');
        Route::delete('/books/{id}/cover', [BookCoverController::class, 'destroy'])->whereUuid('id');

        Route::get('/reports/most-borrowed', [ReportController::class, 'mostBorrowed']);
    });

    Route::middleware($staff)->group(function (): void {
        Route::get('/members', [MemberController::class, 'index']);
        Route::get('/members/{id}', [MemberController::class, 'show'])->whereUuid('id');
        Route::post('/members', [MemberController::class, 'store']);

        Route::post('/loans', [LoanController::class, 'store']);
        Route::get('/loans', [LoanController::class, 'index']);
        Route::get('/loans/overdue', [LoanController::class, 'overdue']);
        Route::get('/loans/{id}', [LoanController::class, 'show'])->whereUuid('id');
        Route::post('/loans/{loan_id}/items/{item_id}/return', [LoanController::class, 'returnItem'])
            ->whereUuid(['loan_id', 'item_id']);
    });
});
