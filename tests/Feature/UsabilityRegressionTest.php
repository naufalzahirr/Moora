<?php

namespace Tests\Feature;

use App\Models\Criterion;
use App\Models\MooraRun;
use App\Models\Period;
use App\Models\Product;
use App\Models\PurchaseOrder;
use App\Models\RestockAction;
use App\Models\Sale;
use App\Models\User;
use DOMDocument;
use DOMXPath;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UsabilityRegressionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    private function owner(): User
    {
        return User::where('role', 'owner')->firstOrFail();
    }

    private function staff(): User
    {
        return User::where('role', 'staff')->firstOrFail();
    }

    private function html(string $html): DOMXPath
    {
        $doc = new DOMDocument;
        @$doc->loadHTML($html);

        return new DOMXPath($doc);
    }

    private function draft(): Period
    {
        $source = Period::firstOrFail();
        $this->actingAs($this->owner())->post(route('datasets.revise', $source))->assertRedirect();

        return Period::where('revision_of_id', $source->id)->firstOrFail();
    }

    public function test_staff_cannot_reopen_owner_decisions_through_single_or_bulk_updates(): void
    {
        $run = MooraRun::firstOrFail();
        $results = $run->results()->limit(2)->get();
        foreach (['approved', 'skipped'] as $index => $status) {
            $result = $results[$index];
            $this->actingAs($this->owner())->put(route('restock-actions.update', [$run, $result]), [
                'status' => $status, 'approved_quantity' => 25,
            ])->assertSessionHas('success');
            $before = $result->restockAction()->firstOrFail()->getAttributes();
            $page = $this->actingAs($this->staff())->get(route('restock-actions.index', ['run' => $run]));
            $checkbox = $this->html($page->getContent())->query('//input[@id="select-restock-'.$result->id.'"]')->item(0);
            $this->assertTrue($checkbox->hasAttribute('disabled'));
            $this->put(route('restock-actions.update', [$run, $result]), ['status' => 'proposed', 'approved_quantity' => 10])->assertSessionHasErrors('restock');
            $this->put(route('restock-actions.bulk-update', $run), ['status' => 'proposed', 'result_ids' => [$result->id]])->assertSessionHasErrors('restock');
            $this->assertSame($before, $result->restockAction()->firstOrFail()->getAttributes());
        }
    }

    public function test_bulk_approval_saves_visible_selected_amounts_and_notes_only(): void
    {
        $run = MooraRun::firstOrFail();
        $results = $run->results()->limit(2)->get();
        $this->actingAs($this->owner())->put(route('restock-actions.update', [$run, $results[0]]), ['status' => 'pending', 'approved_quantity' => 25])->assertSessionHas('success');
        $this->put(route('restock-actions.bulk-update', $run), [
            'status' => 'approved', 'input_mode' => 'edited', 'result_ids' => [$results[0]->id],
            'rows' => [
                $results[0]->id => ['approved_quantity' => 10, 'notes' => 'Jumlah yang ditinjau'],
                $results[1]->id => ['approved_quantity' => 'belum diisi', 'notes' => 'Tidak dipilih'],
            ],
        ])->assertSessionHas('success');
        $this->assertDatabaseHas('restock_actions', ['moora_result_id' => $results[0]->id, 'approved_quantity' => 10, 'notes' => 'Jumlah yang ditinjau', 'status' => 'approved']);
        $this->assertDatabaseMissing('restock_actions', ['moora_result_id' => $results[1]->id]);
    }

    public function test_invalid_bulk_amount_rolls_back_the_entire_selection(): void
    {
        $run = MooraRun::firstOrFail();
        $results = $run->results()->limit(2)->get();
        $this->actingAs($this->owner())->put(route('restock-actions.bulk-update', $run), [
            'status' => 'approved', 'input_mode' => 'edited', 'result_ids' => $results->pluck('id')->all(),
            'rows' => [$results[0]->id => ['approved_quantity' => 10], $results[1]->id => ['approved_quantity' => 1.5]],
        ])->assertSessionHasErrors('rows.'.$results[1]->id.'.approved_quantity');
        $this->assertDatabaseCount('restock_actions', 0);
    }

    public function test_missing_edited_amount_never_falls_back_to_a_saved_suggestion(): void
    {
        $run = MooraRun::firstOrFail();
        $result = $run->results()->firstOrFail();
        $this->actingAs($this->owner())->put(route('restock-actions.bulk-update', $run), [
            'status' => 'approved', 'input_mode' => 'edited', 'result_ids' => [$result->id],
        ])->assertSessionHasErrors('rows.'.$result->id);
        $this->assertDatabaseCount('restock_actions', 0);
    }

    public function test_staff_bulk_update_skips_locked_decisions_and_saves_other_proposals(): void
    {
        $run = MooraRun::firstOrFail();
        $results = $run->results()->limit(2)->get();
        $this->actingAs($this->owner())->put(route('restock-actions.update', [$run, $results[0]]), ['status' => 'approved', 'approved_quantity' => 25])->assertSessionHas('success');
        $this->actingAs($this->staff())->put(route('restock-actions.bulk-update', $run), [
            'status' => 'proposed', 'input_mode' => 'edited', 'result_ids' => $results->pluck('id')->all(),
            'rows' => [$results[0]->id => ['approved_quantity' => 1], $results[1]->id => ['approved_quantity' => 10]],
        ])->assertSessionHas('success');
        $this->assertDatabaseHas('restock_actions', ['moora_result_id' => $results[0]->id, 'status' => 'approved', 'approved_quantity' => 25]);
        $this->assertDatabaseHas('restock_actions', ['moora_result_id' => $results[1]->id, 'status' => 'proposed', 'approved_quantity' => 10]);
    }

    public function test_failed_product_form_restores_only_its_own_dialog(): void
    {
        $product = Product::firstOrFail();
        $form = 'edit-product-'.$product->id;
        $this->actingAs($this->owner())->from(route('products.index'))->put(route('products.update', $product), [
            '_form' => $form, 'code' => '', 'name' => 'NAMA BELUM TERSIMPAN', 'unit' => 'pcs',
        ])->assertSessionHasErrors('code');
        $response = $this->get(route('products.index'))->assertOk()->assertSee('data-invalid-form="'.$form.'"', false);
        $xpath = $this->html($response->getContent());
        $this->assertSame(1, $xpath->query('//dialog//input[@name="name" and @value="NAMA BELUM TERSIMPAN"]')->length);
        $this->assertSame(1, $xpath->query('//dialog[@id="'.$form.'"]//input[@name="name" and @value="NAMA BELUM TERSIMPAN"]')->length);
        $this->assertDatabaseMissing('products', ['name' => 'NAMA BELUM TERSIMPAN']);
    }

    public function test_incomplete_data_can_be_saved_as_a_draft_without_turning_blanks_into_zero(): void
    {
        $draft = $this->draft();
        $sale = $draft->sales()->firstOrFail();
        $other = $draft->sales()->whereKeyNot($sale->id)->firstOrFail();
        $before = $other->getAttributes();
        $this->put(route('datasets.update'), [
            'period_id' => $draft->id, 'next' => 'stay',
            'rows' => [$sale->product_id => ['sold_quantity' => '', 'sales_value' => '12.000', 'ending_stock' => '']],
        ])->assertSessionHas('success');
        $this->assertNull($sale->fresh()->sold_quantity);
        $this->assertEquals(12000, $sale->fresh()->sales_value);
        $this->assertNull($draft->stockMovements()->where('product_id', $sale->product_id)->firstOrFail()->ending_stock);
        $this->assertSame('draft', $draft->fresh()->status);
        $this->assertSame($before, $other->fresh()->getAttributes());
        $this->post(route('calculations.store'), ['period_id' => $draft->id])->assertSessionHasErrors('dataset');
        $this->assertSame(0, $draft->runs()->count());
    }

    public function test_a_partial_sales_value_is_not_treated_as_zero_even_when_stock_is_complete(): void
    {
        $draft = $this->draft();
        $sale = $draft->sales()->firstOrFail();
        $sale->update(['sales_value' => null]);
        $this->post(route('calculations.store'), ['period_id' => $draft->id])->assertSessionHasErrors('dataset');
        $this->assertSame(0, $draft->runs()->count());
    }

    public function test_manual_data_starts_unfilled_and_cannot_include_products_from_another_period(): void
    {
        $this->actingAs($this->owner())->post(route('datasets.periods.store'), [
            'name' => 'Draft baru', 'start_date' => '2026-08-24', 'end_date' => '2026-08-25',
        ])->assertRedirect();
        $draft = Period::where('name', 'Draft baru')->firstOrFail();
        $this->assertTrue($draft->sales->every(fn ($sale) => $sale->sold_quantity === null && $sale->sales_value === null));
        $extra = Product::create(['name' => 'Barang lain', 'code' => 'NOT-IN-PERIOD', 'unit' => 'pcs', 'active' => true]);
        $this->put(route('datasets.update'), ['period_id' => $draft->id, 'next' => 'stay', 'rows' => [$extra->id => ['sold_quantity' => 1, 'sales_value' => 1000, 'ending_stock' => 10]]])->assertSessionHasErrors('rows');
    }

    public function test_empty_report_filters_have_no_unrelated_summary_or_downloads(): void
    {
        $run = MooraRun::firstOrFail();
        $this->actingAs($this->owner())->get(route('reports.index', ['q' => 'TIDAK-ADA', 'run' => $run->id]))
            ->assertOk()->assertViewHas('runs', fn ($runs) => $runs->total() === 0)->assertViewHas('run', null)
            ->assertDontSee(route('reports.download', $run), false)->assertDontSee(route('exports.run', $run), false);
    }

    public function test_low_stock_and_missing_configuration_are_visible_together(): void
    {
        Product::where('code', 'GL01')->update(['minimum_stock' => 1000]);
        $this->actingAs($this->owner())->get(route('dashboard'))->assertOk()
            ->assertViewHas('lowStockCount', fn ($count) => $count > 0)
            ->assertSee('STOK DI BAWAH MINIMUM')->assertSee('barang belum memiliki stok minimum.');
        $this->get(route('inventory.index', ['stock' => 'low']))->assertOk()
            ->assertViewHas('products', fn ($products) => $products->total() === 1);
    }

    public function test_dashboard_omits_completed_actions_and_counts_current_active_products(): void
    {
        $run = MooraRun::firstOrFail();
        $result = $run->results()->where('restock_quantity', '>', 0)->firstOrFail();
        RestockAction::create(['moora_result_id' => $result->id, 'status' => 'received']);
        Product::create(['name' => 'Baru', 'code' => 'NEW-ACTIVE', 'unit' => 'pcs', 'active' => true]);
        $this->actingAs($this->owner())->get(route('dashboard'))
            ->assertViewHas('productCount', 6)
            ->assertViewHas('priorities', fn ($priorities) => ! $priorities->contains('id', $result->id));
    }

    public function test_purchase_preview_counts_suppliers_instead_of_items(): void
    {
        $run = MooraRun::firstOrFail();
        $results = $run->results()->where('restock_quantity', '>', 0)->limit(2)->get();
        $this->actingAs($this->owner())->put(route('restock-actions.bulk-update', $run), ['status' => 'approved', 'result_ids' => $results->pluck('id')->all()])->assertSessionHas('success');
        $this->get(route('restock-actions.index', ['run' => $run]))->assertOk()->assertSee('2 barang · 1 supplier')->assertSee('Buat 1 Draft Pesanan')->assertDontSee('Buat 2 Pesanan');
        $this->post(route('purchase-orders.from-run', $run))->assertRedirect();
        $this->assertSame(1, PurchaseOrder::count());
    }

    public function test_recommendations_and_operational_data_paginate_large_periods(): void
    {
        $draft = $this->draft();
        for ($index = 0; $index < 60; $index++) {
            $product = Product::create(['name' => 'Barang '.$index, 'code' => 'PAGE-'.$index, 'unit' => 'pcs', 'active' => true]);
            Sale::create(['period_id' => $draft->id, 'product_id' => $product->id]);
        }
        $this->get(route('datasets.index', ['period' => $draft, 'page' => 2]))->assertOk()
            ->assertViewHas('sales', fn ($sales) => $sales->total() === 65 && $sales->count() === 15);
        $run = MooraRun::firstOrFail();
        $this->get(route('restock-actions.index', ['run' => $run, 'q' => 'Gula']))->assertViewHas('results', fn ($results) => $results->total() === 1);
        $this->get(route('calculations.results', ['run' => $run, 'q' => 'Gula']))->assertViewHas('results', fn ($results) => $results->total() === 1);
    }

    public function test_owner_can_enter_percent_weights_without_changing_the_calculation_scale(): void
    {
        $criteria = Criterion::orderBy('code')->get();
        $rows = $criteria->mapWithKeys(fn ($criterion) => [$criterion->id => [
            'type' => $criterion->type, 'weight' => (float) $criterion->weight * 100, 'active' => true,
        ]])->all();
        $this->actingAs($this->owner())->put(route('criteria.update'), ['weight_unit' => 'percent', 'criteria' => $rows])->assertSessionHas('success');
        $this->assertEqualsWithDelta(1.0, Criterion::sum('weight'), 0.000001);
        $this->assertEquals(0.4, $criteria->first()->fresh()->weight);
    }

    public function test_decimal_and_local_currency_inputs_keep_their_value_and_reject_negative_amounts(): void
    {
        $draft = $this->draft();
        $sale = $draft->sales()->firstOrFail();
        foreach (['7159600.00' => 7159600, '7.159.600' => 7159600, '1.250,50' => 1250.5] as $input => $expected) {
            $this->put(route('datasets.update'), [
                'period_id' => $draft->id, 'next' => 'stay',
                'rows' => [$sale->product_id => ['sold_quantity' => 10, 'ending_stock' => 10, 'sales_value' => $input]],
            ])->assertSessionHas('success');
            $this->assertEquals($expected, $sale->fresh()->sales_value);
        }
        $this->put(route('datasets.update'), [
            'period_id' => $draft->id, 'next' => 'stay',
            'rows' => [$sale->product_id => ['sold_quantity' => 10, 'ending_stock' => 10, 'sales_value' => '-1.000']],
        ])->assertSessionHasErrors('rows.'.$sale->product_id.'.sales_value');
        $this->assertEquals(1250.5, $sale->fresh()->sales_value);
    }

    public function test_historical_names_remain_searchable_after_master_data_changes(): void
    {
        $run = MooraRun::firstOrFail();
        Product::where('code', 'GL01')->update(['name' => 'Nama master baru', 'code' => 'RENAMED']);
        $this->actingAs($this->owner())->get(route('calculations.results', ['run' => $run, 'q' => 'Gula']))
            ->assertOk()->assertViewHas('results', fn ($results) => $results->total() === 1);
        $this->get(route('restock-actions.index', ['run' => $run, 'q' => 'GL01']))
            ->assertOk()->assertViewHas('results', fn ($results) => $results->total() === 1);
    }
}
