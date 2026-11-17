<?php

namespace App\Http\Controllers;

use App\Http\Requests\PaginationRequest;
use App\Http\Requests\StoreProductRequest;
use App\Http\Requests\UpdateProductRequest;
use App\Http\Resources\ProductResource;
use App\Models\Product;
use App\Models\StockMovement;
use App\Services\StockService;
use Illuminate\Support\Facades\DB;

class ProductController extends Controller
{
    public function index(PaginationRequest $request)
    {
        $perPage = $request->integer('per_page', 15);
        $products = Product::orderBy('id')->paginate($perPage);

        return ProductResource::collection($products);
    }

    public function store(StoreProductRequest $request, StockService $stock)
    {
        $data = $request->validated();
        $quantity = $request->integer('quantity', 0);

        $product = DB::transaction(function () use ($data, $quantity, $request, $stock) {
            $product = new Product;
            $product->name = $data['name'];
            $product->description = $data['description'] ?? null;
            $product->code = $data['code'] ?? null;
            $product->price = $data['price'];
            $product->save();

            $product->refresh();

            if ($quantity > 0) {
                $stock->move(
                    $product,
                    $request->user(),
                    StockMovement::ENTRY,
                    $quantity,
                    'Estoque inicial'
                );
            }

            return $product;
        });

        $response = new ProductResource($product);

        return $response->response()->setStatusCode(201);
    }

    public function show(Product $product)
    {
        return new ProductResource($product);
    }

    public function update(UpdateProductRequest $request, Product $product)
    {
        $data = $request->validated();
        $product->update($data);
        $product->refresh();

        return new ProductResource($product);
    }

    public function destroy(Product $product)
    {
        $product->delete();

        return response()->json(['message' => 'Produto excluido.']);
    }
}
