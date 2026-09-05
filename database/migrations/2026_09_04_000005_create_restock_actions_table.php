<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('restock_actions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('moora_result_id')->unique()->constrained()->cascadeOnDelete();
            $table->enum('status', ['pending', 'ordered', 'received', 'skipped'])->default('pending');
            $table->decimal('approved_quantity', 18, 2)->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('processed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('ordered_at')->nullable();
            $table->timestamp('received_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('restock_actions');
    }
};
