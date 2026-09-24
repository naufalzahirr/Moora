<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use LogicException;

/** Local demo setup, including master data, opening stocks, and transactions. */
class CompleteDemoSeeder extends Seeder
{
    public function run(): void
    {
        if (app()->environment('production')) {
            throw new LogicException('Demo lengkap hanya untuk local/testing. Tidak boleh dijalankan pada production.');
        }

        DB::transaction(function (): void {
            if (! User::exists()) {
                $this->call(DatabaseSeeder::class);
            }
            $this->call([
                TransactionDemoSeeder::class,
                HistoricalTransactionDemoSeeder::class,
                ThesisStockCorrectionSeeder::class,
            ]);
        });
    }
}
