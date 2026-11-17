<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\StockController;
use Illuminate\Support\Facades\Route;

Route::post('/register', [AuthController::class, 'register'])->middleware('throttle:10,1');
Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:10,1');

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/me', [AuthController::class, 'me']);
    Route::get('/user', [AuthController::class, 'me']);
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::apiResource('products', ProductController::class);
    Route::get('/products/{product}/stock', [StockController::class, 'show']);
    Route::get('/products/{product}/movements', [StockController::class, 'index']);
    Route::post('/products/{product}/entries', [StockController::class, 'entry']);
    Route::post('/products/{product}/exits', [StockController::class, 'exit']);
});
