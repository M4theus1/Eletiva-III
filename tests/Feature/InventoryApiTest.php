<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\StockMovement;
use App\Models\User;
use App\Services\StockService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Tests\TestCase;

class InventoryApiTest extends TestCase
{
    use RefreshDatabase;

    private function api(string $method, string $uri, array $data = [], ?string $token = null)
    {
        $this->app['auth']->forgetGuards();

        return $this->json($method, $uri, $data, $token ? ['Authorization' => 'Bearer '.$token] : []);
    }

    private function token(): string
    {
        return User::factory()->create()->createToken('test')->plainTextToken;
    }

    private function product(array $attributes = []): Product
    {
        $product = Product::create(array_merge(['name' => 'Teclado', 'price' => '49.90'], $attributes));

        return $product->refresh();
    }

    public function test_registration_returns_token_and_safe_user_resource(): void
    {
        $response = $this->api('POST', '/api/register', [
            'name' => 'Ana',
            'email' => 'ana@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ])->assertCreated()->assertJsonPath('data.name', 'Ana')->assertJsonPath('token_type', 'Bearer');

        $response->assertJsonMissingPath('data.password')->assertJsonMissingPath('data.remember_token');
        $this->assertTrue(Hash::check('password123', User::first()->password));
        $this->api('GET', '/api/me', [], $response->json('token'))->assertOk()->assertJsonPath('data.email', 'ana@example.com');
        $this->assertDatabaseCount('personal_access_tokens', 1);
        $this->assertNotSame($response->json('token'), User::first()->tokens()->first()->token);
    }

    public function test_registration_rejects_duplicate_email_and_invalid_fields(): void
    {
        $user = User::factory()->create();
        $this->api('POST', '/api/register', [
            'name' => ' ',
            'email' => $user->email,
            'password' => 'short',
            'password_confirmation' => 'different',
        ])->assertUnprocessable()->assertJsonValidationErrors(['name', 'email', 'password']);
        $this->assertDatabaseCount('users', 1);
        $this->assertDatabaseCount('personal_access_tokens', 0);
        $this->api('POST', '/api/register', [])->assertUnprocessable()->assertJsonValidationErrors(['name', 'email', 'password']);
    }

    public function test_login_and_logout_revoke_only_the_current_token(): void
    {
        $user = User::factory()->create(['password' => 'password123']);
        $other = $user->createToken('other-device')->plainTextToken;
        $token = $this->api('POST', '/api/login', [
            'email' => $user->email, 'password' => 'password123',
        ])->assertOk()->json('token');
        $this->api('GET', '/api/me', [], $token)->assertOk()->assertJsonPath('data.id', $user->id);
        $this->api('POST', '/api/logout', [], $token)->assertOk()->assertJsonStructure(['message']);
        $this->api('GET', '/api/me', [], $token)->assertUnauthorized();
        $this->api('POST', '/api/logout', [], $token)->assertUnauthorized();
        $this->api('GET', '/api/me', [], $other)->assertOk();
        $this->assertDatabaseCount('personal_access_tokens', 1);
    }

    public function test_login_rejects_wrong_password_unknown_user_and_missing_fields(): void
    {
        $user = User::factory()->create();
        $this->api('POST', '/api/login', ['email' => $user->email, 'password' => 'wrong'])->assertUnauthorized();
        $this->api('POST', '/api/login', ['email' => 'unknown@example.com', 'password' => 'wrong'])->assertUnauthorized();
        $this->api('POST', '/api/login', [])->assertUnprocessable()->assertJsonValidationErrors(['email', 'password']);
        $this->assertDatabaseCount('personal_access_tokens', 0);
    }

    public static function protectedRoutes(): array
    {
        return [
            ['GET', '/api/me'], ['GET', '/api/user'], ['POST', '/api/logout'],
            ['GET', '/api/products'], ['POST', '/api/products'],
            ['GET', '/api/products/1'], ['PUT', '/api/products/1'],
            ['PATCH', '/api/products/1'], ['DELETE', '/api/products/1'],
            ['GET', '/api/products/1/stock'], ['GET', '/api/products/1/movements'],
            ['POST', '/api/products/1/entries'], ['POST', '/api/products/1/exits'],
        ];
    }

    #[DataProvider('protectedRoutes')]
    public function test_management_requires_authentication_even_without_json_accept(string $method, string $uri): void
    {
        $this->call($method, $uri, [], [], [], ['HTTP_ACCEPT' => '*/*'])
            ->assertUnauthorized()->assertHeader('Content-Type', 'application/json')->assertJsonStructure(['message']);
    }

    public function test_invalid_bearer_token_is_rejected(): void
    {
        $this->api('GET', '/api/products', [], 'invalid-token')->assertUnauthorized();
    }

    public function test_product_crud_and_soft_delete_preserve_history(): void
    {
        $token = $this->token();
        $id = $this->api('POST', '/api/products', [
            'name' => 'Mouse', 'code' => 'MOU-001', 'description' => 'USB', 'price' => 25.5, 'quantity' => 2,
        ], $token)->assertCreated()->assertJsonPath('data.price', '25.50')->assertJsonPath('data.quantity', 2)->json('data.id');

        $this->api('GET', '/api/products?per_page=1', [], $token)->assertOk()->assertJsonPath('meta.total', 1)->assertJsonCount(1, 'data');
        $this->api('GET', '/api/products/'.$id, [], $token)->assertOk()->assertJsonPath('data.code', 'MOU-001');
        $this->api('PUT', '/api/products/'.$id, [
            'name' => 'Mouse atualizado', 'price' => 0, 'code' => 'MOU-001', 'description' => null,
        ], $token)->assertOk()->assertJsonPath('data.price', '0.00')->assertJsonPath('data.quantity', 2);
        $this->api('PATCH', '/api/products/'.$id, ['name' => 'Mouse final'], $token)->assertOk()->assertJsonPath('data.name', 'Mouse final');
        $this->api('DELETE', '/api/products/'.$id, [], $token)->assertOk()->assertJsonStructure(['message']);
        $this->assertSoftDeleted('products', ['id' => $id]);
        $this->assertDatabaseCount('stock_movements', 1);
        $this->api('GET', '/api/products', [], $token)->assertOk()->assertJsonCount(0, 'data');
        foreach (['', '/stock', '/movements'] as $suffix) {
            $this->api('GET', '/api/products/'.$id.$suffix, [], $token)->assertNotFound();
        }
        $this->api('POST', '/api/products/'.$id.'/entries', ['quantity' => 1], $token)->assertNotFound();
    }

    public function test_product_defaults_to_zero_stock_and_optional_fields_can_be_omitted(): void
    {
        $token = $this->token();
        $this->api('POST', '/api/products', ['name' => 'Cabo', 'price' => 0], $token)
            ->assertCreated()->assertJsonPath('data.quantity', 0)->assertJsonPath('data.code', null);
        $this->assertDatabaseCount('stock_movements', 0);
    }

    public static function invalidProducts(): array
    {
        return [
            [['name' => null], 'name'], [['name' => '   '], 'name'],
            [['price' => -1], 'price'], [['price' => null], 'price'],
            [['price' => 'abc'], 'price'], [['price' => 1.234], 'price'],
            [['price' => 100000000], 'price'], [['quantity' => -1], 'quantity'],
            [['quantity' => 1.5], 'quantity'], [['quantity' => 2147483648], 'quantity'],
        ];
    }

    #[DataProvider('invalidProducts')]
    public function test_invalid_product_is_not_persisted(array $invalid, string $field): void
    {
        $this->api('POST', '/api/products', array_merge(['name' => 'Produto', 'price' => 10], $invalid), $this->token())
            ->assertUnprocessable()->assertJsonValidationErrors($field);
        $this->assertDatabaseCount('products', 0);
    }

    public function test_unique_code_and_update_validations(): void
    {
        $token = $this->token();
        $product = $this->product(['code' => 'UNIQUE']);
        $other = $this->product(['code' => 'OTHER']);
        $this->api('POST', '/api/products', ['name' => 'Outro', 'price' => 1, 'code' => 'UNIQUE'], $token)
            ->assertUnprocessable()->assertJsonValidationErrors('code');
        $this->api('PATCH', '/api/products/'.$other->id, ['code' => 'UNIQUE'], $token)
            ->assertUnprocessable()->assertJsonValidationErrors('code');
        $this->api('PATCH', '/api/products/'.$product->id, ['name' => '', 'price' => -1], $token)
            ->assertUnprocessable()->assertJsonValidationErrors(['name', 'price']);
        foreach ([0, 10, null] as $quantity) {
            $this->api('PATCH', '/api/products/'.$product->id, ['quantity' => $quantity], $token)
                ->assertUnprocessable()->assertJsonValidationErrors('quantity');
        }
        $this->assertSame(0, $product->fresh()->quantity);
    }

    public function test_entries_exits_stock_and_history(): void
    {
        $token = $this->token();
        $id = $this->api('POST', '/api/products', ['name' => 'Produto', 'price' => 10, 'quantity' => 10], $token)
            ->assertCreated()->json('data.id');
        $this->api('POST', '/api/products/'.$id.'/entries', ['quantity' => 5, 'notes' => 'Compra'], $token)
            ->assertCreated()->assertJsonPath('data.type', 'entry')->assertJsonPath('data.quantity_after', 15);
        $this->api('POST', '/api/products/'.$id.'/exits', ['quantity' => 3], $token)
            ->assertCreated()->assertJsonPath('data.type', 'exit')->assertJsonPath('data.quantity_after', 12);
        $this->api('GET', '/api/products/'.$id.'/stock', [], $token)->assertOk()->assertJsonPath('data.quantity', 12);
        $this->api('GET', '/api/products/'.$id.'/movements', [], $token)->assertOk()
            ->assertJsonPath('meta.total', 3)->assertJsonPath('data.0.quantity_after', 12);
        $this->assertDatabaseCount('stock_movements', 3);
    }

    public function test_insufficient_stock_does_not_change_balance_or_history_and_exact_balance_is_allowed(): void
    {
        $token = $this->token();
        $id = $this->api('POST', '/api/products', ['name' => 'Produto', 'price' => 10, 'quantity' => 2], $token)->json('data.id');
        $this->api('POST', '/api/products/'.$id.'/exits', ['quantity' => 3], $token)
            ->assertUnprocessable()->assertJsonValidationErrors('quantity');
        $this->assertDatabaseHas('products', ['id' => $id, 'quantity' => 2]);
        $this->assertDatabaseCount('stock_movements', 1);
        $this->api('POST', '/api/products/'.$id.'/exits', ['quantity' => 2], $token)
            ->assertCreated()->assertJsonPath('data.quantity_after', 0);
        $this->api('POST', '/api/products/'.$id.'/exits', ['quantity' => 1], $token)->assertUnprocessable();
        $this->assertDatabaseCount('stock_movements', 2);
    }

    public static function invalidQuantities(): array
    {
        return [[0], [-1], [1.5], ['abc'], [null], [2147483648]];
    }

    #[DataProvider('invalidQuantities')]
    public function test_invalid_movements_do_not_change_stock(mixed $quantity): void
    {
        $token = $this->token();
        $product = $this->product();
        foreach (['entries', 'exits'] as $operation) {
            $this->api('POST', '/api/products/'.$product->id.'/'.$operation, ['quantity' => $quantity], $token)
                ->assertUnprocessable()->assertJsonValidationErrors('quantity');
        }
        $this->assertSame(0, $product->fresh()->quantity);
        $this->assertDatabaseCount('stock_movements', 0);
    }

    public function test_stock_cannot_overflow(): void
    {
        $token = $this->token();
        $id = $this->api('POST', '/api/products', ['name' => 'Produto', 'price' => 0, 'quantity' => 2147483647], $token)
            ->assertCreated()->json('data.id');
        $this->api('POST', '/api/products/'.$id.'/entries', ['quantity' => 1], $token)
            ->assertUnprocessable()->assertJsonValidationErrors('quantity');
        $this->assertDatabaseHas('products', ['id' => $id, 'quantity' => 2147483647]);
        $this->assertDatabaseCount('stock_movements', 1);
    }

    public function test_stock_is_rolled_back_if_recording_the_movement_fails(): void
    {
        $product = $this->product();
        $user = User::factory()->create();
        $event = 'eloquent.creating: '.StockMovement::class;
        Event::listen($event, fn () => throw new RuntimeException('Simulated storage failure'));
        try {
            app(StockService::class)->move($product, $user, StockMovement::ENTRY, 3);
            $this->fail('The simulated insert must fail.');
        } catch (RuntimeException $exception) {
            $this->assertSame('Simulated storage failure', $exception->getMessage());
        } finally {
            Event::forget($event);
        }
        $this->assertSame(0, $product->fresh()->quantity);
        $this->assertDatabaseCount('stock_movements', 0);
    }

    public function test_stale_product_instances_cannot_oversell_stock(): void
    {
        $product = $this->product();
        $user = User::factory()->create();
        $stock = app(StockService::class);
        $stock->move($product, $user, StockMovement::ENTRY, 5);
        $first = $product->fresh();
        $second = $product->fresh();
        $stock->move($first, $user, StockMovement::EXIT, 4);
        try {
            $stock->move($second, $user, StockMovement::EXIT, 4);
            $this->fail('A stale model must not allow an excessive exit.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('quantity', $exception->errors());
        }
        $this->assertSame(1, $product->fresh()->quantity);
        $this->assertDatabaseCount('stock_movements', 2);
    }

    public function test_errors_are_json_for_non_json_clients(): void
    {
        $this->get('/api/unknown', ['Accept' => 'text/html'])->assertNotFound()->assertHeader('Content-Type', 'application/json');
        $this->post('/api/register', [], ['Accept' => '*/*'])->assertUnprocessable()->assertHeader('Content-Type', 'application/json');
        $this->get('/api/login', ['Accept' => '*/*'])->assertStatus(405)->assertHeader('Content-Type', 'application/json');
        $token = $this->token();
        $this->get('/api/products/999', ['Accept' => '*/*', 'Authorization' => 'Bearer '.$token])
            ->assertNotFound()->assertHeader('Content-Type', 'application/json');
    }

    public function test_invalid_pagination_is_rejected(): void
    {
        $token = $this->token();
        $this->api('GET', '/api/products?per_page=101&page=0', [], $token)
            ->assertUnprocessable()->assertJsonValidationErrors(['per_page', 'page']);
    }

    public function test_users_share_the_company_inventory(): void
    {
        $first = $this->token();
        $second = $this->token();
        $id = $this->api('POST', '/api/products', ['name' => 'Compartilhado', 'price' => 1], $first)->json('data.id');
        $this->api('POST', '/api/products/'.$id.'/entries', ['quantity' => 2], $second)->assertCreated();
        $this->api('GET', '/api/products/'.$id.'/stock', [], $first)->assertOk()->assertJsonPath('data.quantity', 2);
    }
}
