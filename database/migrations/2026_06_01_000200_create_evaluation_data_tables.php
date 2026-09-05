<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('periods', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->date('start_date');
            $table->date('end_date');
            $table->string('source_file')->nullable();
            $table->enum('status', ['draft', 'ready', 'completed'])->default('draft');
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->timestamp('calculated_at')->nullable();
            $table->timestamps();
        });

        Schema::create('sales', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('period_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->decimal('sold_quantity', 18, 2)->default(0);
            $table->decimal('sales_value', 20, 2)->default(0);
            $table->decimal('manual_yi', 20, 8)->nullable();
            $table->string('source_reference')->nullable();
            $table->timestamps();
            $table->unique(['period_id', 'product_id']);
        });

        Schema::create('stock_movements', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('period_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->decimal('ending_stock', 18, 2)->default(0);
            $table->string('source')->default('Input stok akhir');
            $table->foreignId('recorded_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();
            $table->unique(['period_id', 'product_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_movements');
        Schema::dropIfExists('sales');
        Schema::dropIfExists('periods');
    }
};
