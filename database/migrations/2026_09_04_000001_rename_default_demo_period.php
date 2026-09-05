<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('periods')
            ->where('name', 'Pengujian Juni-Agustus 2026')
            ->update(['name' => 'Periode Juni-Agustus 2026']);
    }

    public function down(): void
    {
        DB::table('periods')
            ->where('name', 'Periode Juni-Agustus 2026')
            ->update(['name' => 'Pengujian Juni-Agustus 2026']);
    }
};
