<?php

namespace App\Support;

use Carbon\Carbon;
use Illuminate\Http\Request;

class MonthlyInput
{
    public static function prepare(Request $request): void
    {
        if (! $request->has('month')) {
            return;
        }
        $validated = validator($request->only('month'), [
            'month' => ['required', 'date_format:Y-m', 'before_or_equal:'.now()->format('Y-m')],
        ])->validate();
        $start = Carbon::createFromFormat('!Y-m', $validated['month']);
        $end = $start->copy()->endOfMonth()->min(now());
        $request->merge([
            'name' => 'Penjualan '.$start->translatedFormat('F Y'),
            'start_date' => $start->toDateString(),
            'end_date' => $end->toDateString(),
        ]);
    }
}
