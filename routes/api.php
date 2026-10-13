<?php

use App\Adapters\Inbound\Http\HealthController;
use Illuminate\Support\Facades\Route;

Route::get('/health', HealthController::class);
