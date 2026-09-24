<?php

namespace Tests\Feature;

use App\Models\MooraRun;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MooraExplanationTest extends TestCase
{
    use RefreshDatabase;

    public function test_steps_use_all_snapshot_rows_even_when_ranking_is_filtered(): void
    {
        $this->seed();
        $run = MooraRun::firstOrFail();
        $this->actingAs(User::where('role', 'owner')->first())->get(route('calculations.results', ['run' => $run, 'q' => 'Gula']))
            ->assertOk()->assertSee('1. Data Awal')->assertSee('2. Normalisasi')->assertSee('3. Pembobotan')
            ->assertSee('4. Nilai Optimasi')->assertSee('5. Ranking')->assertSee('93,370231')
            ->assertSee('0,362102')->assertSee('-0,003451')
            ->assertViewHas('calculationRows', fn ($rows) => $rows->count() === 5)
            ->assertViewHas('results', fn ($rows) => $rows->total() === 1);
    }
}
