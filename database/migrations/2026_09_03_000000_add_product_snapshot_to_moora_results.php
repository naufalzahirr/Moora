<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('moora_results', function (Blueprint $table): void {
            $table->string('product_code_snapshot', 80)->nullable()->after('product_id');
            $table->string('product_name_snapshot')->nullable()->after('product_code_snapshot');
        });

        DB::table('moora_results')
            ->join('products', 'products.id', '=', 'moora_results.product_id')
            ->select('moora_results.id', 'products.code', 'products.name')
            ->orderBy('moora_results.id')
            ->each(function (object $result): void {
                DB::table('moora_results')->where('id', $result->id)->update([
                    'product_code_snapshot' => $result->code,
                    'product_name_snapshot' => $result->name,
                ]);
            });
    }

    public function down(): void
    {
        Schema::table('moora_results', function (Blueprint $table): void {
            $table->dropColumn(['product_code_snapshot', 'product_name_snapshot']);
        });
    }
};
