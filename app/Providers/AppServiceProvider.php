<?php

namespace App\Providers;

use App\Models\Criterion;
use App\Models\MooraRun;
use App\Models\Period;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;
use Illuminate\View\View as IlluminateView;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        Carbon::setLocale('id');

        View::composer('layouts.app', function (IlluminateView $view): void {
            $data = $view->getData();
            $navigationRun = ($data['run'] ?? null) instanceof MooraRun ? $data['run'] : null;
            $navigationPeriod = ($data['period'] ?? null) instanceof Period
                ? $data['period']
                : $navigationRun?->period;
            $navigationPeriod ??= MooraRun::with('period')->latest('id')->first()?->period;
            $navigationPeriod ??= Period::latest('updated_at')->latest('id')->first();

            $view->with([
                'navigationPeriod' => $navigationPeriod,
                'navigationCriteriaCount' => $navigationRun
                    ? count($navigationRun->criteria_snapshot ?? [])
                    : Criterion::active()->count(),
                'navigationAlternativeCount' => $navigationRun?->total_alternatives
                    ?? $navigationPeriod?->sales()->count()
                    ?? 0,
            ]);
        });
    }
}
