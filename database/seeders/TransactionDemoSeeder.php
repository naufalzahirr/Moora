<?php

namespace Database\Seeders;

use App\Models\Product;
use App\Models\StockTransaction;
use App\Models\User;
use App\Services\TransactionService;
use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/** Explicitly invoked demo only; never attached to DatabaseSeeder or deployment. */
class TransactionDemoSeeder extends Seeder
{
    public const START = '2026-09-01';

    public const END = '2026-09-21';

    public function run(): void
    {
        if (now()->toDateString() < self::END) {
            throw new RuntimeException('Tanggal simulasi belum selesai; seeder tidak membuat transaksi masa depan.');
        }

        DB::transaction(function (): void {
            $user = User::where('active', true)->where('role', 'owner')->firstOrFail();
            // Quantities are packs/units, including a 5-pack as one unit of Indomie.
            // Prices are illustrative selling prices, not claims about actual store prices.
            $scenarios = [
                'GL01' => ['opening' => 110, 'receipt' => 60, 'price' => 17000, 'week' => [4, 5, 4, 6, 7, 9, 8]],
                '8993379256371' => ['opening' => 70, 'receipt' => 40, 'price' => 18500, 'week' => [3, 4, 3, 4, 5, 6, 5]],
                'Minangrasa01' => ['opening' => 80, 'receipt' => 20, 'price' => 15000, 'week' => [2, 2, 3, 2, 3, 4, 3]],
                '089686010824' => ['opening' => 100, 'receipt' => 80, 'price' => 16000, 'week' => [5, 6, 5, 7, 8, 10, 9]],
                '8886008101053' => ['opening' => 150, 'receipt' => 0, 'price' => 3500, 'week' => [2, 3, 2, 3, 4, 6, 5]],
            ];
            $products = Product::where('active', true)->orderBy('id')->lockForUpdate()->get()->keyBy('code');
            if ($products->count() !== count($scenarios) || array_diff(array_keys($scenarios), $products->keys()->all())) {
                throw new RuntimeException('Seeder ini khusus lima barang contoh awal. Tidak ada barang atau data pengguna yang diubah.');
            }
            $entries = [];
            foreach ($scenarios as $code => $scenario) {
                $product = $products[$code];
                $entries[] = $this->entry($product->id, $code, 'opening', self::START, $scenario['opening']);
                for ($date = CarbonImmutable::parse(self::START); $date->toDateString() <= self::END; $date = $date->addDay()) {
                    if ($date->toDateString() === '2026-09-08' && $scenario['receipt'] > 0) {
                        $entries[] = $this->entry($product->id, $code, 'receipt', $date->toDateString(), $scenario['receipt']);
                    }
                    $quantity = $scenario['week'][$date->dayOfWeekIso - 1];
                    $entries[] = $this->entry($product->id, $code, 'sale', $date->toDateString(), $quantity, $quantity * $scenario['price']);
                }
            }
            $keys = array_column($entries, 'submission_key');
            $existing = StockTransaction::whereIn('submission_key', $keys)->count();
            if ($existing === count($entries)) {
                $this->command?->info('Simulasi sudah tersedia. Tidak menambah transaksi atau hasil lagi.');

                return;
            }
            if (StockTransaction::whereIn('product_id', $products->pluck('id'))->exists()) {
                throw new RuntimeException('Sudah ada transaksi atau simulasi parsial. Seeder dibatalkan agar tidak mencampur stok awal dengan data yang sudah ada.');
            }
            $service = app(TransactionService::class);
            foreach ($entries as $entry) {
                $service->record($entry, $user);
            }
            $run = $service->calculate(self::START, self::END, $user);
            $run->update(['notes' => 'SIMULASI untuk demonstrasi skripsi, bukan transaksi aktual toko. Penjualan harian dan harga contoh disusun deterministik. '.$run->notes]);
            $this->command?->info(count($entries).' transaksi SIMULASI tersimpan; hasil MOORA #'.$run->id.' dibuat untuk '.self::START.' sampai '.self::END.'.');
        });
    }

    private function entry(int $productId, string $code, string $type, string $date, int $quantity, int $value = 0): array
    {
        $hash = md5('h2-demo-transactions-v1/'.$code.'/'.$type.'/'.$date);
        $key = substr($hash, 0, 8).'-'.substr($hash, 8, 4).'-5'.substr($hash, 13, 3).'-a'.substr($hash, 17, 3).'-'.substr($hash, 20, 12);

        return [
            'submission_key' => $key, 'product_id' => $productId, 'type' => $type,
            'occurred_on' => $date, 'quantity' => $quantity, 'sales_value' => $value,
            'notes' => 'SIMULASI SKRIPSI · '.match ($type) {
                'opening' => 'Stok awal sebelum penjualan 1 September.',
                'receipt' => 'Kiriman supplier setelah minggu pertama.',
                'sale' => 'Total penjualan harian; harga contoh tetap, bukan transaksi aktual.',
            },
        ];
    }
}
