<?php

namespace App\Http\Controllers;

use App\Http\Requests\PaginationRequest;
use App\Http\Requests\StockMovementRequest;
use App\Http\Resources\StockMovementResource;
use App\Http\Resources\StockResource;
use App\Models\Product;
use App\Models\StockMovement;
use App\Services\StockService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class StockController extends Controller
{
    public function show(Product $product): StockResource
    {
        return new StockResource($product);
    }

    public function index(PaginationRequest $request, Product $product): AnonymousResourceCollection
    {
        return StockMovementResource::collection(
            $product->movements()->orderByDesc('id')->paginate($request->integer('per_page', 15))
        );
    }

    public function entry(StockMovementRequest $request, Product $product, StockService $stock): JsonResponse
    {
        return $this->move($request, $product, $stock, StockMovement::ENTRY);
    }

    public function exit(StockMovementRequest $request, Product $product, StockService $stock): JsonResponse
    {
        return $this->move($request, $product, $stock, StockMovement::EXIT);
    }

    private function move(StockMovementRequest $request, Product $product, StockService $stock, string $type): JsonResponse
    {
        $movement = $stock->move(
            $product,
            $request->user(),
            $type,
            $request->integer('quantity'),
            $request->validated('notes')
        );

        return (new StockMovementResource($movement))->response()->setStatusCode(201);
    }
}
