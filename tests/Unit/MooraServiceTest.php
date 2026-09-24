<?php

namespace Tests\Unit;

use App\Models\MooraRun;
use App\Models\Period;
use App\Models\Product;
use App\Models\Sale;
use App\Models\StockMovement;
use App\Services\MooraService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MooraServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_sample_data_matches_the_thesis_mockup_ranking(): void
    {
        $this->seed();

        $calculation = app(MooraService::class)->calculate(Period::firstOrFail());
        $rows = $calculation['rows'];

        $this->assertSame([
            'Gula Pasir 1kg',
            'Minyak Kita 1 Liter Bantal',
            'Indomie Goreng 5pack',
            'Beras Minang Rasa 1kg',
            'Aqua 600ml',
        ], $rows->pluck('product.name')->all());

        $this->assertEqualsWithDelta(0.36210232, $rows[0]['yi'], 0.0001);
        $this->assertEqualsWithDelta(0.16159534, $rows[1]['yi'], 0.0001);
        $this->assertEqualsWithDelta(0.12014980, $rows[2]['yi'], 0.0001);
        $this->assertEqualsWithDelta(-0.00345070, $rows[3]['yi'], 0.0001);
        $this->assertEqualsWithDelta(-0.33980721, $rows[4]['yi'], 0.0001);
    }

    public function test_seeded_manual_and_system_results_are_fully_consistent(): void
    {
        $this->seed();

        $run = MooraRun::with('results')->firstOrFail();

        $this->assertSame('completed', $run->status);
        $this->assertSame(5, $run->total_alternatives);
        $this->assertSame(5, $run->matched_alternatives);
        $this->assertEquals(100, (float) $run->accuracy);
        $this->assertTrue($run->results->every(fn ($result) => $result->matches));
    }

    public function test_seeded_recommendations_include_restock_quantities(): void
    {
        $this->seed();

        $result = MooraRun::with('results')->firstOrFail()->results->sortBy('rank_system')->first();

        $this->assertNotNull($result->restock_target);
        $this->assertNotNull($result->restock_quantity);
        $this->assertGreaterThan(0, (float) $result->restock_quantity);
    }

    public function test_tied_yi_values_are_ranked_by_product_code_for_system_and_manual_results(): void
    {
        $this->seed();
        $period = Period::firstOrFail();
        $first = Product::where('code', 'GL01')->firstOrFail();
        $second = Product::where('code', '8993379256371')->firstOrFail();

        foreach ([$first, $second] as $product) {
            Sale::where('period_id', $period->id)->where('product_id', $product->id)->update([
                'sold_quantity' => 150,
                'sales_value' => 1500000,
            ]);
            StockMovement::where('period_id', $period->id)->where('product_id', $product->id)->update(['ending_stock' => 20]);
        }

        $service = app(MooraService::class);
        $calculation = $service->calculate($period);
        $byCode = $calculation['rows']->keyBy(fn (array $row): string => $row['product']->code);

        $this->assertEqualsWithDelta($byCode[$first->code]['yi'], $byCode[$second->code]['yi'], 0.0000000001);
        $this->assertLessThan($byCode[$first->code]['rank'], $byCode[$second->code]['rank']);

        $manualValues = $calculation['rows']->mapWithKeys(
            fn (array $row): array => [$row['product']->id => $row['yi']]
        )->all();
        $run = $service->execute($period, $period->creator, $manualValues);
        $results = $run->results->keyBy('product_id');

        $this->assertSame($results[$first->id]->rank_system, $results[$first->id]->rank_manual);
        $this->assertSame($results[$second->id]->rank_system, $results[$second->id]->rank_manual);
    }

    public function test_restock_recommendation_uses_the_product_target_and_is_snapshotted_in_the_run(): void
    {
        $this->seed();
        $period = Period::firstOrFail();
        $product = Product::where('code', 'GL01')->firstOrFail();
        $product->update(['minimum_stock' => 25, 'target_stock' => 100]);

        $service = app(MooraService::class);
        $calculation = $service->calculate($period);
        $row = $calculation['rows']->first(fn (array $row): bool => $row['product']->id === $product->id);

        $this->assertSame(100.0, $row['restock_target']);
        $this->assertSame(65.0, $row['restock_quantity']);

        $manualValues = $calculation['rows']->mapWithKeys(
            fn (array $item): array => [$item['product']->id => $item['yi']]
        )->all();
        $run = $service->execute($period, $period->creator, $manualValues);
        $result = $run->results()->where('product_id', $product->id)->firstOrFail();

        $this->assertEquals(100, (float) $result->restock_target);
        $this->assertEquals(65, (float) $result->restock_quantity);
    }

    public function test_restock_quantity_uses_daily_demand_supplier_lead_time_safety_stock_and_order_multiple(): void
    {
        $this->seed();
        $period = Period::firstOrFail();
        $product = Product::where('code', 'GL01')->firstOrFail();
        $product->supplier->update(['lead_time_days' => 3]);
        $product->update([
            'minimum_stock' => 0,
            'safety_stock' => 10,
            'target_stock' => null,
            'review_period_days' => 5,
            'minimum_order_quantity' => 20,
            'order_multiple' => 12,
        ]);
        Sale::where('period_id', $period->id)->where('product_id', $product->id)->update(['sold_quantity' => 84]);
        StockMovement::where('period_id', $period->id)->where('product_id', $product->id)->update(['ending_stock' => 4]);

        $row = app(MooraService::class)->calculate($period)['rows']
            ->first(fn (array $item): bool => $item['product']->id === $product->id);

        // 84 units / 84 hari = 1 per hari; target = 1 × (3 + 5) + safety 10 = 18.
        // Kekurangan 14 dibulatkan ke kelipatan 12 sehingga rekomendasi menjadi 24.
        $this->assertSame(18.0, $row['restock_target']);
        $this->assertSame(24.0, $row['restock_quantity']);
        $this->assertSame(1.0, $row['restock_basis']['average_daily_demand']);
        $this->assertSame(8, $row['restock_basis']['lead_time_days'] + $row['restock_basis']['review_period_days']);
    }
}
