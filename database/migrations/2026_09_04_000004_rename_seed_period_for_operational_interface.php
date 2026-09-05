<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('periods')
            ->whereIn('name', [
                'Pengujian Juni-Agustus 2026',
                'Data Demo Juni-Agustus 2026',
                'Periode Juni-Agustus 2026',
            ])
            ->update(['name' => 'Data Penjualan Tercatat']);

        DB::table('criteria')
            ->where('source_description', 'Data uji stok akhir')
            ->update(['source_description' => 'Input stok akhir']);
    }

    public function down(): void
    {
        DB::table('periods')
            ->where('name', 'Data Penjualan Tercatat')
            ->whereDate('start_date', '2026-06-01')
            ->whereDate('end_date', '2026-08-23')
            ->update(['name' => 'Periode Juni-Agustus 2026']);
    }
};
