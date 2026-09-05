<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('restock_actions', function (Blueprint $table): void {
            $table->enum('status', ['pending', 'proposed', 'approved', 'ordered', 'received', 'skipped'])
                ->default('pending')
                ->change();
            $table->foreignId('proposed_by')->nullable()->after('processed_by')->constrained('users')->nullOnDelete();
            $table->timestamp('proposed_at')->nullable()->after('proposed_by');
            $table->foreignId('approved_by')->nullable()->after('proposed_at')->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable()->after('approved_by');
        });

        // Runs made before live inventory was introduced have a stock value in the
        // immutable MOORA input snapshot, but no restock basis. Copy it once so
        // historical recommendations never misleadingly show stock as zero.
        DB::table('moora_results')
            ->join('moora_runs', 'moora_runs.id', '=', 'moora_results.moora_run_id')
            ->whereNull('moora_results.restock_basis')
            ->orderBy('moora_results.id')
            ->select([
                'moora_results.id',
                'moora_results.raw_values',
                'moora_results.restock_target',
                'moora_runs.criteria_snapshot',
            ])
            ->each(function (object $result): void {
                $criteria = json_decode((string) $result->criteria_snapshot, true) ?: [];
                $stockCriterion = collect($criteria)->first(
                    fn (array $criterion): bool => ($criterion['source'] ?? $criterion['value_source'] ?? null) === 'ending_stock'
                );
                $rawValues = json_decode((string) $result->raw_values, true) ?: [];
                $stockCode = $stockCriterion['code'] ?? 'C1';
                $onHand = (float) ($rawValues[$stockCode] ?? 0);

                DB::table('moora_results')->where('id', $result->id)->update([
                    'restock_basis' => json_encode([
                        'version' => 1,
                        'source' => 'historical_period_snapshot',
                        'on_hand' => $onHand,
                        'incoming' => 0,
                        'projected_available' => $onHand,
                        'target' => $result->restock_target === null ? null : (float) $result->restock_target,
                    ], JSON_THROW_ON_ERROR),
                ]);
            });
    }

    public function down(): void
    {
        Schema::table('restock_actions', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('proposed_by');
            $table->dropColumn('proposed_at');
            $table->dropConstrainedForeignId('approved_by');
            $table->dropColumn('approved_at');
            $table->enum('status', ['pending', 'approved', 'ordered', 'received', 'skipped'])
                ->default('pending')
                ->change();
        });
    }
};
