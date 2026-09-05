<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table): void {
            $table->decimal('safety_stock', 18, 2)->default(0)->after('minimum_stock');
            $table->unsignedSmallInteger('review_period_days')->default(7)->after('target_stock');
            $table->decimal('minimum_order_quantity', 18, 2)->default(0)->after('review_period_days');
            $table->decimal('order_multiple', 18, 2)->default(1)->after('minimum_order_quantity');
        });

        Schema::create('purchase_orders', function (Blueprint $table): void {
            $table->id();
            $table->string('order_number', 80)->unique();
            $table->foreignId('supplier_id')->constrained()->restrictOnDelete();
            $table->foreignId('moora_run_id')->nullable()->constrained()->nullOnDelete();
            $table->enum('status', ['draft', 'approved', 'ordered', 'partial', 'received', 'cancelled'])->default('draft');
            $table->date('expected_at')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('ordered_at')->nullable();
            $table->timestamp('received_at')->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['status', 'expected_at']);
        });

        Schema::create('purchase_order_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('purchase_order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->restrictOnDelete();
            $table->foreignId('moora_result_id')->nullable()->constrained()->nullOnDelete();
            $table->decimal('suggested_quantity', 18, 2)->nullable();
            $table->decimal('ordered_quantity', 18, 2);
            $table->decimal('received_quantity', 18, 2)->default(0);
            $table->decimal('unit_price', 20, 2)->nullable();
            $table->timestamps();
            $table->unique(['purchase_order_id', 'product_id']);
        });

        Schema::table('restock_actions', function (Blueprint $table): void {
            $table->foreignId('purchase_order_id')->nullable()->after('moora_result_id')->constrained()->nullOnDelete();
            $table->enum('status', ['pending', 'approved', 'ordered', 'received', 'skipped'])->default('pending')->change();
        });

        Schema::create('inventory_movements', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('product_id')->constrained()->restrictOnDelete();
            $table->foreignId('period_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('purchase_order_item_id')->nullable()->constrained()->nullOnDelete();
            $table->enum('movement_type', ['opening_balance', 'sale', 'receipt', 'adjustment'])->default('adjustment');
            $table->decimal('quantity_change', 18, 2);
            $table->date('occurred_on');
            $table->string('reference', 120)->nullable();
            $table->text('notes')->nullable();
            $table->json('metadata')->nullable();
            $table->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['product_id', 'occurred_on']);
            $table->index(['period_id', 'movement_type']);
        });

        Schema::table('moora_results', function (Blueprint $table): void {
            $table->json('restock_basis')->nullable()->after('restock_quantity');
        });

        // Existing installations used period-end stock as a snapshot. Preserve the
        // newest known quantity as the first live-stock balance, rather than adding
        // every old snapshot together.
        $latestStocks = DB::table('stock_movements')
            ->join('periods', 'periods.id', '=', 'stock_movements.period_id')
            ->orderByDesc('periods.end_date')
            ->orderByDesc('stock_movements.id')
            ->get(['stock_movements.product_id', 'stock_movements.ending_stock', 'periods.end_date'])
            ->unique('product_id');

        foreach ($latestStocks as $stock) {
            DB::table('inventory_movements')->insert([
                'product_id' => $stock->product_id,
                'period_id' => null,
                'purchase_order_item_id' => null,
                'movement_type' => 'opening_balance',
                'quantity_change' => $stock->ending_stock,
                'occurred_on' => $stock->end_date,
                'reference' => 'Migrasi saldo awal',
                'notes' => 'Saldo awal stok berjalan dari stok akhir data operasional terbaru.',
                'metadata' => json_encode(['migrated' => true]),
                'recorded_by' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        Schema::table('moora_results', function (Blueprint $table): void {
            $table->dropColumn('restock_basis');
        });

        Schema::dropIfExists('inventory_movements');

        Schema::table('restock_actions', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('purchase_order_id');
        });

        Schema::dropIfExists('purchase_order_items');
        Schema::dropIfExists('purchase_orders');

        Schema::table('products', function (Blueprint $table): void {
            $table->dropColumn(['safety_stock', 'review_period_days', 'minimum_order_quantity', 'order_multiple']);
        });
    }
};
