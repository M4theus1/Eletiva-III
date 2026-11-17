<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StockMovement extends Model
{
    use HasFactory;

    public const ENTRY = 'entry';

    public const EXIT = 'exit';

    protected $fillable = ['user_id', 'type', 'quantity', 'quantity_before', 'quantity_after', 'notes'];

    protected function casts(): array
    {
        return ['quantity' => 'integer', 'quantity_before' => 'integer', 'quantity_after' => 'integer'];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class)->withTrashed();
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
