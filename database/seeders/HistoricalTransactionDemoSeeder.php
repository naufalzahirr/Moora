<?php

namespace Database\Seeders;

use App\Models\Period;
use App\Models\Product;
use App\Models\StockTransaction;
use App\Models\User;
use App\Services\TransactionService;
use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class HistoricalTransactionDemoSeeder extends Seeder
{
    public function run(): void
    {
        DB::transaction(function (): void {
            Product::orderBy('id')->lockForUpdate()->get();
            if (StockTransaction::where('notes', 'like', 'SIMULASI HISTORIS v1%')->exists()) {
                $this->command?->info('Simulasi historis sudah tersedia; tidak ada data digandakan.');

                return;
            }
            $source = Period::where('source_type', 'manual')->whereNull('revision_of_id')
                ->whereDate('start_date', '2026-06-01')->whereDate('end_date', '2026-08-23')
                ->with(['sales.product', 'stockMovements'])->firstOrFail();
            $user = User::where('active', true)->where('role', 'owner')->firstOrFail();
            $stocks = $source->stockMovements->keyBy('product_id');
            $days = collect();
            for ($date = CarbonImmutable::parse('2026-06-01'); $date->toDateString() <= '2026-08-23'; $date = $date->addDay()) {
                $days->push(['date' => $date->toDateString(), 'weight' => $date->isWeekend() ? 3 : 2]);
            }
            foreach ($source->sales as $sale) {
                $endStock = $stocks->get($sale->product_id)?->ending_stock;
                if ($endStock === null || $sale->sold_quantity === null || $sale->sales_value === null) {
                    throw new RuntimeException('Rekap lama belum lengkap. Tidak ada transaksi yang diubah.');
                }
                $history = StockTransaction::where('product_id', $sale->product_id)->orderBy('occurred_on')->orderBy('id')->get();
                $opening = $history->first();
                if ($history->contains(fn ($entry) => ! str_starts_with($entry->notes ?? '', 'SIMULASI SKRIPSI'))
                    || ($opening && ($opening->type !== 'opening' || $opening->occurred_on->toDateString() !== '2026-09-01'))) {
                    throw new RuntimeException('Seeder hanya dapat menyambungkan simulasi September yang belum dicampur transaksi pengguna.');
                }
                $sold = (float) $sale->sold_quantity;
                $scale = $sale->product->usesWholeUnits() ? 1 : 100;
                $totalUnits = (int) round($sold * $scale);
                $totalCents = (int) round((float) $sale->sales_value * 100);
                if ($totalUnits === 0 && $totalCents > 0) {
                    throw new RuntimeException('Nilai penjualan tanpa jumlah terjual tidak dapat dibagi menjadi transaksi.');
                }
                $this->insert($sale->product_id, 'opening', '2026-06-01', $sold + (float) $endStock, 0, $user->id,
                    'Saldo awal sintetis; diasumsikan tidak ada penerimaan selama rentang lama.');
                $weight = 0;
                $allocated = 0;
                $allocatedCents = 0;
                foreach ($days as $day) {
                    $weight += $day['weight'];
                    $target = (int) floor($totalUnits * $weight / $days->sum('weight'));
                    $quantity = $target - $allocated;
                    if ($quantity === 0) {
                        continue;
                    }
                    $targetCents = (int) round($totalCents * $target / $totalUnits);
                    $this->insert($sale->product_id, 'sale', $day['date'], $quantity / $scale, ($targetCents - $allocatedCents) / 100, $user->id,
                        'Pembagian sintetis rekap lama; bukan tanggal transaksi aktual.');
                    $allocated = $target;
                    $allocatedCents = $targetCents;
                }
                if ($opening) {
                    $difference = (float) $opening->quantity - (float) $endStock;
                    $opening->update([
                        'type' => $difference >= 0 ? 'increase' : 'decrease',
                        'quantity' => abs($difference),
                        'notes' => 'SIMULASI SKRIPSI · Penyesuaian saldo ke stok awal September semula; bukan penjualan aktual.',
                    ]);
                }
            }
            $run = app(TransactionService::class)->calculate('2026-06-01', '2026-08-23', $user);
            $run->update(['notes' => 'SIMULASI HISTORIS: pembagian harian sintetis dari rekap lama, bukan bukti transaksi aktual. '.$run->notes]);
            $this->command?->info('Simulasi historis ditambahkan dan hasil MOORA #'.$run->id.' dibuat. Saldo September dipertahankan.');
        });
    }

    private function insert(int $product, string $type, string $date, float $quantity, float $value, int $user, string $note): void
    {
        $hash = md5('historical-demo-v1/'.$product.'/'.$type.'/'.$date);
        StockTransaction::create([
            'submission_key' => substr($hash, 0, 8).'-'.substr($hash, 8, 4).'-5'.substr($hash, 13, 3).'-a'.substr($hash, 17, 3).'-'.substr($hash, 20),
            'product_id' => $product, 'type' => $type, 'occurred_on' => $date,
            'quantity' => $quantity, 'sales_value' => $value, 'recorded_by' => $user,
            'notes' => 'SIMULASI HISTORIS v1 · '.$note,
        ]);
    }
}
