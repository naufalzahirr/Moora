<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Criterion;
use App\Models\Period;
use App\Models\Product;
use App\Models\Report;
use App\Models\Sale;
use App\Models\StockMovement;
use App\Models\Supplier;
use App\Models\User;
use App\Services\MooraReportService;
use App\Services\MooraService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        if (app()->environment('production')) {
            throw new \LogicException('DatabaseSeeder berisi data pengembangan dan tidak boleh dijalankan pada production. Buat akun awal melalui prosedur provisioning yang aman.');
        }

        $owner = User::create([
            'name' => 'Owner H2 Asia',
            'username' => 'owner',
            'email' => 'owner@h2asia.local',
            'role' => 'owner',
            'password' => Hash::make('password'),
        ]);
        $staff = User::create([
            'name' => 'Petugas Persediaan',
            'username' => 'petugas',
            'email' => 'petugas@h2asia.local',
            'role' => 'staff',
            'password' => Hash::make('password'),
        ]);

        $supplier = Supplier::create([
            'name' => 'Distributor Utama H2 Asia',
            'phone' => '0771-000000',
            'address' => 'Tanjungpinang, Kepulauan Riau',
            'lead_time_days' => 7,
            'active' => true,
        ]);

        $categories = collect(['Sembako', 'Makanan Instan', 'Minuman'])
            ->mapWithKeys(fn (string $name): array => [$name => Category::create(['name' => $name])]);

        Criterion::insert([
            [
                'code' => 'C1', 'name' => 'Stok Akhir', 'type' => 'cost', 'weight' => 0.40,
                'value_source' => 'ending_stock', 'source_description' => 'Input stok akhir', 'active' => true,
                'created_at' => now(), 'updated_at' => now(),
            ],
            [
                'code' => 'C2', 'name' => 'Jumlah Barang Terjual', 'type' => 'benefit', 'weight' => 0.35,
                'value_source' => 'sold_quantity', 'source_description' => 'Laporan aktual - Kts. Standar', 'active' => true,
                'created_at' => now(), 'updated_at' => now(),
            ],
            [
                'code' => 'C3', 'name' => 'Nilai Penjualan', 'type' => 'benefit', 'weight' => 0.25,
                'value_source' => 'sales_value', 'source_description' => 'Laporan aktual - Nilai barang', 'active' => true,
                'created_at' => now(), 'updated_at' => now(),
            ],
        ]);

        $sample = [
            ['code' => 'GL01', 'name' => 'Gula Pasir 1kg', 'category' => 'Sembako', 'stock' => 35, 'sold' => 484, 'sales' => 7159600],
            ['code' => '8993379256371', 'name' => 'Minyak Kita 1 Liter Bantal', 'category' => 'Sembako', 'stock' => 18, 'sold' => 204, 'sales' => 3802500],
            ['code' => 'Minangrasa01', 'name' => 'Beras Minang Rasa 1kg', 'category' => 'Sembako', 'stock' => 22, 'sold' => 98, 'sales' => 1448700],
            ['code' => '089686010824', 'name' => 'Indomie Goreng 5pack', 'category' => 'Makanan Instan', 'stock' => 12, 'sold' => 163, 'sales' => 2380800],
            ['code' => '8886008101053', 'name' => 'Aqua 600ml', 'category' => 'Minuman', 'stock' => 80, 'sold' => 4, 'sales' => 14000],
        ];

        $period = Period::create([
            'name' => 'Data Penjualan Tercatat',
            'start_date' => '2026-06-01',
            'end_date' => '2026-08-23',
            'source_file' => 'Ringkasan Penjualan per Barang.xlsx',
            'status' => 'ready',
            'created_by' => $staff->id,
        ]);

        foreach ($sample as $row) {
            $product = Product::create([
                'code' => $row['code'],
                'name' => $row['name'],
                'unit' => 'pcs',
                'category_id' => $categories[$row['category']]->id,
                'supplier_id' => $supplier->id,
                'active' => true,
            ]);
            Sale::create([
                'period_id' => $period->id,
                'product_id' => $product->id,
                'sold_quantity' => $row['sold'],
                'sales_value' => $row['sales'],
                'source_reference' => $period->source_file,
            ]);
            StockMovement::create([
                'period_id' => $period->id,
                'product_id' => $product->id,
                'ending_stock' => $row['stock'],
                'source' => 'Input stok akhir',
                'recorded_by' => $staff->id,
            ]);
        }

        $service = app(MooraService::class);
        $manualValues = $service->calculate($period)['rows']->mapWithKeys(
            fn (array $row): array => [$row['product']->id => round($row['yi'], 8)]
        )->all();

        foreach ($manualValues as $productId => $yi) {
            Sale::where('period_id', $period->id)->where('product_id', $productId)->update(['manual_yi' => $yi]);
        }

        $run = $service->execute($period, $owner, $manualValues);
        Report::create([
            'moora_run_id' => $run->id,
            'document_name' => app(MooraReportService::class)->documentName($run),
            'generated_by' => $owner->id,
            'generated_at' => now(),
        ]);
    }
}
