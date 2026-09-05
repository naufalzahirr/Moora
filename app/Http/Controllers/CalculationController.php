<?php

namespace App\Http\Controllers;

use App\Models\MooraResult;
use App\Models\MooraRun;
use App\Models\Period;
use App\Services\ActivityLogger;
use App\Services\MooraService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class CalculationController extends Controller
{
    public function index(Request $request): RedirectResponse
    {
        $parameters = $request->integer('period') ? ['period' => $request->integer('period')] : [];

        return redirect()->route('datasets.index', $parameters);
    }

    public function store(Request $request, MooraService $service, ActivityLogger $logger): RedirectResponse
    {
        $validated = $request->validate(['period_id' => ['required', 'exists:periods,id']]);
        $period = Period::with('sales')->findOrFail($validated['period_id']);
        if ($period->isLocked()) {
            throw ValidationException::withMessages([
                'period' => 'Data ini sudah tersimpan sebagai riwayat. Buat pembaruan pada Data Operasional untuk menghitung versi baru.',
            ]);
        }
        $manualValues = $period->sales->mapWithKeys(
            fn ($sale): array => [$sale->product_id => $sale->manual_yi]
        )->all();

        $run = $service->execute($period, $request->user(), $manualValues);
        $logger->log($request->user(), 'moora.executed', "Membuat rekomendasi restock untuk {$period->displayName()}.", [
            'period_id' => $period->id,
            'run_id' => $run->id,
            'accuracy' => $run->accuracy,
        ]);

        return redirect()->route('calculations.results', $run)
            ->with('success', 'Rekomendasi restock berhasil dibuat dan disimpan.');
    }

    public function results(Request $request, ?MooraRun $run = null): View
    {
        if (! $run && $request->integer('run')) {
            $run = MooraRun::findOrFail($request->integer('run'));
        }
        $run ??= MooraRun::latest('id')->first();
        if ($run) {
            $run->load(['period', 'results' => fn ($query) => $query->with(['product', 'restockAction'])->orderBy('rank_system')]);
        }

        return view('calculations.results', [
            'run' => $run,
            'runs' => MooraRun::with('period')->latest('id')->get(),
        ]);
    }

    public function show(MooraRun $run, MooraResult $result): View
    {
        abort_unless($result->moora_run_id === $run->id, 404);
        $run->load('period');
        $result->load(['product', 'restockAction']);
        $criteria = collect($run->criteria_snapshot)->keyBy('code');

        return view('calculations.show', compact('run', 'result', 'criteria'));
    }
}
