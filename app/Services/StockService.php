<?php

namespace App\Services;

use App\Models\Product;
use App\Models\StockMovement;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;

class StockService
{
    public function move(Product $product, User $user, string $type, int $quantity, ?string $notes = null)
    {
        if ($type !== StockMovement::ENTRY && $type !== StockMovement::EXIT) {
            throw new InvalidArgumentException('Movimentacao invalida.');
        }

        if ($quantity < 1 || $quantity > 2147483647) {
            throw new InvalidArgumentException('Movimentacao invalida.');
        }

        return DB::transaction(function () use ($product, $user, $type, $quantity, $notes) {
            $query = Product::where('id', $product->id);

            if ($type === StockMovement::ENTRY) {
                $maximumStock = 2147483647;
                $changed = $query->where('quantity', '<=', $maximumStock - $quantity)
                    ->increment('quantity', $quantity);

                $errorMessage = 'A entrada excede o limite de estoque permitido.';
            } else {
                $changed = $query->where('quantity', '>=', $quantity)
                    ->decrement('quantity', $quantity);

                $errorMessage = 'Estoque insuficiente para esta saida.';
            }

            if ($changed !== 1) {
                Product::findOrFail($product->id);

                throw ValidationException::withMessages([
                    'quantity' => $errorMessage,
                ]);
            }

            $product->refresh();
            $quantityAfter = $product->quantity;

            if ($type === StockMovement::ENTRY) {
                $quantityBefore = $quantityAfter - $quantity;
            } else {
                $quantityBefore = $quantityAfter + $quantity;
            }

            $movement = $product->movements()->create([
                'user_id' => $user->id,
                'type' => $type,
                'quantity' => $quantity,
                'quantity_before' => $quantityBefore,
                'quantity_after' => $quantityAfter,
                'notes' => $notes,
            ]);

            return $movement;
        }, 3);
    }
}
