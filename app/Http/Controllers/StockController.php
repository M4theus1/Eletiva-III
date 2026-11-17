<?php

namespace App\Http\Controllers;

use App\Http\Requests\PaginationRequest;
use App\Http\Requests\StockMovementRequest;
use App\Http\Resources\StockMovementResource;
use App\Http\Resources\StockResource;
use App\Models\Product;
use App\Models\StockMovement;
use App\Services\StockService;

class StockController extends Controller
{
    public function show(Product $product)
    {
        return new StockResource($product);
    }

    public function index(PaginationRequest $request, Product $product)
    {
        $perPage = $request->integer('per_page', 15);
        $movements = $product->movements()->orderByDesc('id')->paginate($perPage);

        return StockMovementResource::collection($movements);
    }

    public function entry(StockMovementRequest $request, Product $product, StockService $stock)
    {
        $quantity = $request->integer('quantity');
        $notes = $request->validated('notes');

        $movement = $stock->move(
            $product,
            $request->user(),
            StockMovement::ENTRY,
            $quantity,
            $notes
        );

        $response = new StockMovementResource($movement);

        return $response->response()->setStatusCode(201);
    }

    public function exit(StockMovementRequest $request, Product $product, StockService $stock)
    {
        $quantity = $request->integer('quantity');
        $notes = $request->validated('notes');

        $movement = $stock->move(
            $product,
            $request->user(),
            StockMovement::EXIT,
            $quantity,
            $notes
        );

        $response = new StockMovementResource($movement);

        return $response->response()->setStatusCode(201);
    }
}
