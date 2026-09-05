<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('criteria', function (Blueprint $table): void {
            $table->id();
            $table->string('code', 10)->unique();
            $table->string('name');
            $table->enum('type', ['benefit', 'cost']);
            $table->decimal('weight', 8, 6);
            $table->enum('value_source', ['ending_stock', 'sold_quantity', 'sales_value']);
            $table->string('source_description');
            $table->boolean('active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('criteria');
    }
};
