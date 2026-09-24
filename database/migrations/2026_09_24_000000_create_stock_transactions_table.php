<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stock_transactions', function (Blueprint $table): void {
            $table->id();
            $table->uuid('submission_key')->unique();
            $table->foreignId('product_id')->constrained()->restrictOnDelete();
            $table->string('type', 20);
            $table->date('occurred_on');
            $table->decimal('quantity', 18, 2);
            $table->decimal('sales_value', 20, 2)->default(0);
            $table->string('notes', 500)->nullable();
            $table->foreignId('recorded_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();
            $table->index(['product_id', 'occurred_on']);
        });
        Schema::table('periods', function (Blueprint $table): void {
            $table->string('source_type', 20)->default('manual');
            $table->unsignedBigInteger('transaction_cutoff')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('periods', fn (Blueprint $table) => $table->dropColumn(['source_type', 'transaction_cutoff']));
        Schema::dropIfExists('stock_transactions');
    }
};
