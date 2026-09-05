<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table): void {
            $table->decimal('minimum_stock', 18, 2)->default(0)->after('unit');
            $table->decimal('target_stock', 18, 2)->nullable()->after('minimum_stock');
        });

        Schema::table('moora_results', function (Blueprint $table): void {
            $table->decimal('restock_target', 18, 2)->nullable()->after('rank_manual');
            $table->decimal('restock_quantity', 18, 2)->nullable()->after('restock_target');
        });

        Schema::table('periods', function (Blueprint $table): void {
            $table->foreignId('revision_of_id')->nullable()->after('created_by')->constrained('periods')->nullOnDelete();
        });

        Schema::table('users', function (Blueprint $table): void {
            $table->boolean('active')->default(true)->after('role');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn('active');
        });

        Schema::table('periods', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('revision_of_id');
        });

        Schema::table('moora_results', function (Blueprint $table): void {
            $table->dropColumn(['restock_target', 'restock_quantity']);
        });

        Schema::table('products', function (Blueprint $table): void {
            $table->dropColumn(['minimum_stock', 'target_stock']);
        });
    }
};
