<?php

namespace App\Http\Controllers;

use App\Http\Requests\PaginationRequest;
use App\Http\Requests\StoreProductRequest;
use App\Http\Requests\UpdateProductRequest;
use App\Http\Resources\ProductResource;
use App\Models\Product;
use App\Models\StockMovement;
use App\Services\StockService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;

class ProductController extends Controller
{
    public function index(PaginationRequest $request): AnonymousResourceCollection
    {
        return ProductResource::collection(Product::orderBy('id')->paginate($request->integer('per_page', 15)));
    }

    public function store(StoreProductRequest $request, StockService $stock): JsonResponse
    {
        $product = DB::transaction(function () use ($request, $stock) {
            $product = Product::create($request->safe()->except('quantity'));
            $product->refresh();

            if ($request->integer('quantity') > 0) {
                $stock->move($product, $request->user(), StockMovement::ENTRY, $request->integer('quantity'), 'Estoque inicial');
            }

            return $product;
        });

        return (new ProductResource($product))->response()->setStatusCode(201);
    }

    public function show(Product $product): ProductResource
    {
        return new ProductResource($product);
    }

    public function update(UpdateProductRequest $request, Product $product): ProductResource
    {
        $product->update($request->validated());

        return new ProductResource($product->refresh());
    }

    public function destroy(Product $product): JsonResponse
    {
        $product->delete();

        return response()->json(['message' => 'Produto excluido.']);
    }
}
