<?php

namespace Tests\Feature;

use App\Models\Period;
use App\Models\Product;
use App\Models\RestockAction;
use App\Models\User;
use App\Services\MooraService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PurchaseSuggestionTest extends TestCase
{
    use RefreshDatabase;

    public function test_suggestion_respects_minimum_and_multiple_and_preserves_user_proposal(): void
    {
        $this->seed();
        $product = Product::where('code', 'GL01')->firstOrFail();
        $product->update(['target_stock' => 36, 'review_period_days' => 0, 'safety_stock' => 0, 'supplier_id' => null, 'minimum_order_quantity' => 13, 'order_multiple' => 12]);
        $owner = User::where('role', 'owner')->first();
        $run = app(MooraService::class)->execute(Period::first(), $owner);
        $result = $run->results->firstWhere('product_id', $product->id);
        $this->assertEquals(24, $result->restock_quantity);
        $this->actingAs($owner)->get(route('purchases.review', ['run' => $run]))->assertOk()->assertSee('Saran jumlah pembelian: 24 pcs')->assertSee('Bagaimana jumlah ini dihitung?');
        RestockAction::create(['moora_result_id' => $result->id, 'status' => 'proposed', 'approved_quantity' => 48]);
        $this->get(route('purchases.review', ['run' => $run]))->assertOk()->assertSee('value="48.00"', false);
        $product->update(['target_stock' => 35]);
        $rows = app(MooraService::class)->calculate(Period::first())['rows'];
        $this->assertEquals(0, $rows->first(fn ($row) => $row['product']->id === $product->id)['restock_quantity']);
    }
}
