<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\StockMovement;
use App\Models\User;
use App\Services\StockService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class InventoryMigrationTest extends TestCase
{
    public static function previousSchemas(): array
    {
        return [['local'], ['remote']];
    }

    #[DataProvider('previousSchemas')]
    public function test_existing_data_survives_upgrade_from_either_branch(string $source): void
    {
        $this->assertSame(':memory:', config('database.connections.sqlite.database'));
        $this->artisan('migrate', ['--path' => 'database/migrations/0001_01_01_000000_create_users_table.php'])->assertSuccessful();

        foreach (glob(base_path('tests/Fixtures/migrations/'.$source.'/*.php')) as $path) {
            (require $path)->up();
            DB::table('migrations')->insert(['migration' => basename($path, '.php'), 'batch' => 1]);
        }

        $user = User::factory()->create();
        $productId = DB::table('products')->insertGetId([
            'name' => 'Produto existente', 'code' => 'LEGACY', 'price' => '12.50',
            'quantity' => 7, 'created_at' => now(), 'updated_at' => now(),
        ]);
        foreach ([
            ['type' => 'entry', 'quantity' => 10, 'quantity_after' => 10, 'quantity_before' => 0],
            ['type' => 'exit', 'quantity' => 3, 'quantity_after' => 7, 'quantity_before' => 10],
        ] as $movement) {
            if ($source === 'remote') {
                $movement['type'] = $movement['type'] === 'entry' ? 'entrada' : 'saida';
            } else {
                unset($movement['quantity_before']);
            }
            DB::table('stock_movements')->insert($movement + [
                'product_id' => $productId, 'user_id' => $user->id, 'notes' => 'Historico preservado',
                'created_at' => now(), 'updated_at' => now(),
            ]);
        }

        $this->artisan('migrate', ['--force' => true])->assertSuccessful();
        $this->assertTrue(Schema::hasColumn('products', 'deleted_at'));
        $this->assertDatabaseHas('products', ['id' => $productId, 'quantity' => 7, 'code' => 'LEGACY']);
        $this->assertSame([0, 10], StockMovement::orderBy('id')->pluck('quantity_before')->all());
        $this->assertSame(['entry', 'exit'], StockMovement::orderBy('id')->pluck('type')->all());
        $this->assertSame(2, $user->movements()->count());

        $product = Product::findOrFail($productId);
        $movement = app(StockService::class)->move($product, $user, StockMovement::EXIT, 2);
        $this->assertSame(7, $movement->quantity_before);
        $this->assertSame(5, $movement->quantity_after);

        $this->artisan('migrate:rollback', ['--step' => 1, '--force' => true])->assertSuccessful();
        $this->artisan('migrate', ['--force' => true])->assertSuccessful();
        $this->assertSame(5, $product->fresh()->quantity);
        $this->assertDatabaseCount('stock_movements', 3);
        $product->delete();
        $this->assertSoftDeleted('products', ['id' => $productId]);
        $this->assertSame($productId, $movement->fresh()->product->id);
    }
}
