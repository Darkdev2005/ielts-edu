<?php

use App\Http\Controllers\Api\AiRequestController;
use Illuminate\Support\Facades\Route;

Route::post('/ai/requests', [AiRequestController::class, 'store']);
Route::get('/ai/requests/{id}', [AiRequestController::class, 'show']);
