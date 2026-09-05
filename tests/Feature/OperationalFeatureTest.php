<?php

namespace Tests\Feature;

use App\Models\MooraResult;
use App\Models\MooraRun;
use App\Models\Product;
use App\Models\PurchaseOrder;
use App\Models\User;
use App\Services\InventoryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OperationalFeatureTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_can_manage_users_and_inactive_accounts_cannot_sign_in(): void
    {
        $this->seed();
        $owner = User::where('role', 'owner')->firstOrFail();

        $this->actingAs($owner)->post(route('users.store'), [
            'name' => 'Petugas Baru',
            'username' => 'petugasbaru',
            'email' => 'petugasbaru@h2asia.local',
            'role' => 'staff',
            'active' => true,
            'password' => 'password-baru',
            'password_confirmation' => 'password-baru',
        ])->assertSessionHas('success');

        $newUser = User::where('username', 'petugasbaru')->firstOrFail();
        $this->assertTrue($newUser->active);
        $this->actingAs($owner)->delete(route('users.destroy', $newUser))->assertSessionHas('success');
        $this->assertFalse($newUser->fresh()->active);
        $this->post(route('logout'));
        $this->post(route('login.store'), ['username' => 'petugasbaru', 'password' => 'password-baru'])
            ->assertSessionHasErrors('username');

        $this->actingAs(User::where('role', 'staff')->firstOrFail())->get(route('users.index'))->assertForbidden();
    }

    public function test_periods_cannot_overlap_another_base_period(): void
    {
        $this->seed();
        $staff = User::where('role', 'staff')->firstOrFail();

        $this->actingAs($staff)->post(route('datasets.periods.store'), [
            'name' => 'Periode Bertumpang Tindih',
            'start_date' => '2026-08-01',
            'end_date' => '2026-09-01',
        ])->assertSessionHasErrors('start_date');
    }

    public function test_spreadsheets_can_be_exported_for_runs_periods_and_activity_logs(): void
    {
        $this->seed();
        $owner = User::where('role', 'owner')->firstOrFail();
        $run = MooraRun::firstOrFail();

        $this->actingAs($owner)->get(route('exports.run', $run))
            ->assertOk()
            ->assertDownload('Hasil_Restock_MOORA_Run-'.$run->id.'.xlsx');
        $this->actingAs($owner)->get(route('exports.period', $run->period))
            ->assertOk()
            ->assertDownload();
        $this->actingAs($owner)->get(route('exports.activities'))
            ->assertOk()
            ->assertDownload('Log_Aktivitas_H2_Asia.xlsx');
    }

    public function test_staff_proposes_owner_approves_and_receipt_updates_live_stock(): void
    {
        $this->seed();
        $staff = User::where('role', 'staff')->firstOrFail();
        $run = MooraRun::firstOrFail();
        $result = MooraResult::where('moora_run_id', $run->id)->orderBy('rank_system')->firstOrFail();

        $this->actingAs($staff)->get(route('restock-actions.index', ['run' => $run]))
            ->assertOk()
            ->assertSee('Tindak Lanjut Restock')
            ->assertSee('Kirim Usulan ke Owner');

        $this->actingAs($staff)->put(route('restock-actions.update', [$run, $result]), [
            'status' => 'proposed',
            'approved_quantity' => 25,
            'notes' => 'Pesan pada supplier utama.',
        ])->assertSessionHas('success');

        $this->assertDatabaseHas('restock_actions', [
            'moora_result_id' => $result->id,
            'status' => 'proposed',
            'approved_quantity' => 25,
            'processed_by' => $staff->id,
        ]);

        $this->actingAs($staff)->put(route('restock-actions.update', [$run, $result]), [
            'status' => 'approved',
            'approved_quantity' => 25,
        ])->assertForbidden();

        $owner = User::where('role', 'owner')->firstOrFail();
        $this->actingAs($owner)->put(route('restock-actions.update', [$run, $result]), [
            'status' => 'approved',
            'approved_quantity' => 25,
            'notes' => 'Disetujui untuk supplier utama.',
        ])->assertSessionHas('success');

        $this->actingAs($staff)->post(route('purchase-orders.from-run', $run))->assertForbidden();
        $this->actingAs($owner)->post(route('purchase-orders.from-run', $run))->assertRedirect();
        $order = PurchaseOrder::with('items')->firstOrFail();
        $this->assertSame('draft', $order->status);
        $this->assertSame(1, $order->items->count());

        $this->actingAs($owner)->put(route('purchase-orders.status', $order), ['status' => 'approved'])
            ->assertSessionHas('success');
        $this->actingAs($staff)->put(route('purchase-orders.status', $order), ['status' => 'ordered'])
            ->assertSessionHas('success');

        $item = $order->items->first();
        $stockBeforeReceipt = app(InventoryService::class)->onHand($item->product_id);
        $this->actingAs($staff)->put(route('purchase-orders.receive', $order), [
            'items' => [$item->id => 10],
        ])->assertSessionHas('success');

        $this->assertEquals($stockBeforeReceipt + 10, app(InventoryService::class)->onHand($item->product_id));
        $this->assertDatabaseHas('inventory_movements', [
            'purchase_order_item_id' => $item->id,
            'movement_type' => 'receipt',
            'quantity_change' => 10,
        ]);
        $this->assertDatabaseHas('activity_logs', [
            'user_id' => $staff->id,
            'action' => 'purchase_order.received',
        ]);
    }

    public function test_owner_can_bulk_approve_selected_recommendations(): void
    {
        $this->seed();
        $owner = User::where('role', 'owner')->firstOrFail();
        $run = MooraRun::firstOrFail();
        $results = MooraResult::query()
            ->where('moora_run_id', $run->id)
            ->where('restock_quantity', '>', 0)
            ->orderBy('rank_system')
            ->limit(2)
            ->get();

        $this->assertCount(2, $results);
        $this->actingAs($owner)->put(route('restock-actions.bulk-update', $run), [
            'status' => 'approved',
            'result_ids' => $results->pluck('id')->all(),
        ])->assertSessionHas('success');

        foreach ($results as $result) {
            $this->assertDatabaseHas('restock_actions', [
                'moora_result_id' => $result->id,
                'status' => 'approved',
                'approved_quantity' => $result->restock_quantity,
                'processed_by' => $owner->id,
            ]);
        }
        $this->assertDatabaseHas('activity_logs', [
            'user_id' => $owner->id,
            'action' => 'restock.bulk_updated',
        ]);
    }

    public function test_legacy_analysis_route_returns_to_operational_data_and_403_page_has_a_return_path(): void
    {
        $this->seed();
        $staff = User::where('role', 'staff')->firstOrFail();

        $this->actingAs($staff)->get(route('calculations.index'))
            ->assertRedirect(route('datasets.index'));

        $this->actingAs($staff)->get(route('users.index'))
            ->assertForbidden()
            ->assertSee('Akses tidak diizinkan')
            ->assertSee('Kembali ke Dashboard');
    }

    public function test_stock_opname_records_only_the_difference_against_live_inventory(): void
    {
        $this->seed();
        $staff = User::where('role', 'staff')->firstOrFail();
        $product = Product::where('code', 'GL01')->firstOrFail();
        $inventory = app(InventoryService::class);
        $before = $inventory->onHand($product);

        $this->actingAs($staff)->post(route('inventory.adjust'), [
            'product_id' => $product->id,
            'counted_quantity' => 50,
            'occurred_on' => now()->toDateString(),
            'notes' => 'Stock opname rak utama.',
        ])->assertSessionHas('success');

        $this->assertEquals(50, $inventory->onHand($product));
        $this->assertDatabaseHas('inventory_movements', [
            'product_id' => $product->id,
            'movement_type' => 'adjustment',
            'quantity_change' => 50 - $before,
        ]);
    }
}
