<?php

namespace App\Http\Controllers;

use App\Http\Requests\CriterionRequest;
use App\Models\Criterion;
use App\Services\ActivityLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class CriterionController extends Controller
{
    public function index(): View
    {
        $criteria = Criterion::orderBy('code')->get();

        return view('criteria.index', compact('criteria'));
    }

    public function update(CriterionRequest $request, ActivityLogger $logger): RedirectResponse
    {
        $payload = collect($request->validated('criteria'));
        $active = $payload->filter(fn (array $row): bool => (bool) ($row['active'] ?? false));
        $total = $active->sum(fn (array $row): float => (float) $row['weight']);

        if (abs($total - 1.0) > 0.000001) {
            throw ValidationException::withMessages(['criteria' => 'Total bobot kriteria aktif harus tepat 100%.']);
        }
        if (! $active->contains('type', 'benefit') || ! $active->contains('type', 'cost')) {
            throw ValidationException::withMessages(['criteria' => 'Harus ada sedikitnya satu kriteria benefit dan satu cost.']);
        }

        DB::transaction(function () use ($payload): void {
            foreach ($payload as $id => $row) {
                Criterion::findOrFail($id)->update([
                    'type' => $row['type'],
                    'weight' => $row['weight'],
                    'active' => (bool) ($row['active'] ?? false),
                ]);
            }
        });

        $logger->log($request->user(), 'criteria.updated', 'Memperbarui konfigurasi kriteria dan bobot MOORA.');

        return back()->with('success', 'Konfigurasi kriteria berhasil disimpan.');
    }
}
