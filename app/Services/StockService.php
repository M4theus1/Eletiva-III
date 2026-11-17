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
    public function move(Product $product, User $user, string $type, int $quantity, ?string $notes = null): StockMovement
    {
        if (! in_array($type, [StockMovement::ENTRY, StockMovement::EXIT], true) || $quantity < 1 || $quantity > 2147483647) {
            throw new InvalidArgumentException('Movimentacao invalida.');
        }

        return DB::transaction(function () use ($product, $user, $type, $quantity, $notes) {
            // The conditional UPDATE checks and changes the balance in one database operation.
            // Keeping the movement in the same transaction also rolls back stock if its insert fails.
            $query = Product::query()->whereKey($product->id);
            $changed = $type === StockMovement::ENTRY
                ? $query->where('quantity', '<=', 2147483647 - $quantity)->increment('quantity', $quantity)
                : $query->where('quantity', '>=', $quantity)->decrement('quantity', $quantity);

            if ($changed !== 1) {
                Product::query()->findOrFail($product->id);

                throw ValidationException::withMessages([
                    'quantity' => $type === StockMovement::ENTRY
                        ? 'A entrada excede o limite de estoque permitido.'
                        : 'Estoque insuficiente para esta saida.',
                ]);
            }

            $product->refresh();

            return $product->movements()->create([
                'user_id' => $user->id,
                'type' => $type,
                'quantity' => $quantity,
                'quantity_before' => $type === StockMovement::ENTRY ? $product->quantity - $quantity : $product->quantity + $quantity,
                'quantity_after' => $product->quantity,
                'notes' => $notes,
            ]);
        }, 3);
    }
}
