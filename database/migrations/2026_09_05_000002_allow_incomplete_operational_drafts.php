<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sales', function (Blueprint $table): void {
            $table->decimal('sold_quantity', 18, 2)->nullable()->default(null)->change();
            $table->decimal('sales_value', 20, 2)->nullable()->default(null)->change();
        });
        Schema::table('stock_movements', function (Blueprint $table): void {
            $table->decimal('ending_stock', 18, 2)->nullable()->default(null)->change();
        });
    }

    public function down(): void
    {
        // Refuse to turn an unfilled value into a recorded zero on rollback.
        if (DB::table('sales')->whereNull('sold_quantity')->orWhereNull('sales_value')->exists()
            || DB::table('stock_movements')->whereNull('ending_stock')->exists()) {
            throw new RuntimeException('Lengkapi draft sebelum mengembalikan struktur kolom wajib.');
        }

        Schema::table('sales', function (Blueprint $table): void {
            $table->decimal('sold_quantity', 18, 2)->nullable(false)->default(0)->change();
            $table->decimal('sales_value', 20, 2)->nullable(false)->default(0)->change();
        });
        Schema::table('stock_movements', function (Blueprint $table): void {
            $table->decimal('ending_stock', 18, 2)->nullable(false)->default(0)->change();
        });
    }
};
