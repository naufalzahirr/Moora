<?php

namespace Database\Seeders;

use App\Models\Product;
use App\Models\StockTransaction;
use App\Models\User;
use App\Services\TransactionService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class ThesisStockCorrectionSeeder extends Seeder
{
    public function run(): void
    {
        DB::transaction(function (): void {
            $rice = Product::where('code', 'Minangrasa01')->lockForUpdate()->firstOrFail();
            $marker = 'a7530fa0-0681-5211-a5af-430000000025';
            if (StockTransaction::where('submission_key', $marker)->exists()) {
                $this->command?->info('Koreksi BAB III sudah diterapkan.');

                return;
            }
            $history = StockTransaction::where('product_id', $rice->id)->whereDate('occurred_on', '<=', '2026-08-23')->get();
            if ($history->isEmpty() || $history->contains(fn ($entry) => ! str_starts_with($entry->notes ?? '', 'SIMULASI HISTORIS'))) {
                throw new RuntimeException('Koreksi hanya berlaku untuk simulasi historis yang sudah dibuat.');
            }
            $balance = $history->sum(fn ($entry) => in_array($entry->type, ['sale', 'decrease']) ? -(float) $entry->quantity : (float) $entry->quantity);
            if (! in_array($balance, [22.0, 25.0], true)) {
                throw new RuntimeException('Saldo historis sudah berubah; koreksi otomatis dibatalkan.');
            }
            $user = User::where('role', 'owner')->where('active', true)->firstOrFail();
            $difference = 25 - $balance;
            StockTransaction::create([
                'submission_key' => $marker, 'product_id' => $rice->id, 'type' => 'increase',
                'occurred_on' => '2026-08-23', 'quantity' => $difference, 'sales_value' => 0,
                'recorded_by' => $user->id, 'notes' => 'SIMULASI HISTORIS · Koreksi C1 beras menjadi 25 sesuai BAB III Tabel 3.2.',
            ]);
            if ($difference > 0 && StockTransaction::where('product_id', $rice->id)->whereDate('occurred_on', '>=', '2026-09-01')->exists()) {
                StockTransaction::create([
                    'submission_key' => 'a7530fa0-0681-5211-a5af-430000000026', 'product_id' => $rice->id,
                    'type' => 'decrease', 'occurred_on' => '2026-09-01', 'quantity' => $difference, 'sales_value' => 0,
                    'recorded_by' => $user->id, 'notes' => 'SIMULASI · Penyambung saldo agar skenario September tetap sama setelah koreksi BAB III.',
                ]);
            }
            $run = app(TransactionService::class)->calculate('2026-06-01', '2026-08-23', $user);
            $run->update(['notes' => 'SIMULASI HISTORIS dikoreksi mengikuti BAB III Tabel 3.2: C1 beras 25. Hasil lama tetap disimpan.']);
            $this->command?->info('Data sesuai BAB III; hasil baru #'.$run->id.' dibuat tanpa menimpa hasil lama.');
        });
    }
}
