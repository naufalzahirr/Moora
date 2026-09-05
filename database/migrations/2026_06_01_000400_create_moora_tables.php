<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('moora_runs', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('period_id')->constrained()->cascadeOnDelete();
            $table->foreignId('executed_by')->constrained('users')->restrictOnDelete();
            $table->enum('status', ['completed', 'incomplete'])->default('completed');
            $table->unsignedInteger('total_alternatives');
            $table->unsignedInteger('matched_alternatives')->default(0);
            $table->decimal('accuracy', 5, 2)->default(0);
            $table->json('criteria_snapshot');
            $table->json('normalization_divisors');
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('moora_results', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('moora_run_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->restrictOnDelete();
            $table->string('alternative_code', 20);
            $table->json('raw_values');
            $table->json('normalized_values');
            $table->json('weighted_values');
            $table->decimal('yi_system', 20, 8);
            $table->decimal('yi_manual', 20, 8)->nullable();
            $table->decimal('difference', 20, 8)->nullable();
            $table->unsignedInteger('rank_system');
            $table->unsignedInteger('rank_manual')->nullable();
            $table->boolean('matches')->nullable();
            $table->timestamps();
            $table->unique(['moora_run_id', 'product_id']);
        });

        Schema::create('reports', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('moora_run_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('document_name');
            $table->foreignId('generated_by')->constrained('users')->restrictOnDelete();
            $table->timestamp('generated_at');
            $table->timestamps();
        });

        Schema::create('activity_logs', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('action');
            $table->string('description');
            $table->json('metadata')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('activity_logs');
        Schema::dropIfExists('reports');
        Schema::dropIfExists('moora_results');
        Schema::dropIfExists('moora_runs');
    }
};
