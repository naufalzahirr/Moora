<?php

namespace Tests\Feature;

use App\Models\MooraRun;
use App\Models\Period;
use App\Models\Report;
use App\Models\User;
use App\Services\MooraReportService;
use App\Services\MooraService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class QualityHardeningTest extends TestCase
{
    use RefreshDatabase;

    public function test_historical_result_uses_the_product_snapshot_after_master_data_changes(): void
    {
        $this->seed();
        $owner = User::where('role', 'owner')->firstOrFail();
        $run = MooraRun::with(['results.product'])->firstOrFail();
        $result = $run->results->first();
        $originalName = $result->displayProductName();
        $originalCode = $result->displayProductCode();

        $result->product->update(['name' => 'Nama Barang Berubah', 'code' => 'KODE-BERUBAH']);
        $result->refresh()->load('product');

        $this->assertSame($originalName, $result->displayProductName());
        $this->assertSame($originalCode, $result->displayProductCode());
        $this->actingAs($owner)->get(route('calculations.results', $run))->assertOk()->assertSee($originalName)->assertDontSee('Nama Barang Berubah');
        $this->actingAs($owner)->get(route('reports.index', ['run' => $run]))->assertOk()->assertSee($originalName)->assertDontSee('Nama Barang Berubah');
    }

    public function test_report_names_are_unique_per_run(): void
    {
        $this->seed();
        $period = Period::with('sales')->firstOrFail();
        $owner = User::where('role', 'owner')->firstOrFail();
        $firstRun = MooraRun::firstOrFail();
        $secondRun = app(MooraService::class)->execute(
            $period,
            $owner,
            $period->sales->mapWithKeys(fn ($sale): array => [$sale->product_id => $sale->manual_yi])->all(),
        );
        $reporter = app(MooraReportService::class);

        $this->assertNotSame($reporter->documentName($firstRun), $reporter->documentName($secondRun));
        $this->assertStringContainsString('Run-'.$firstRun->id, $reporter->documentName($firstRun));
        $this->assertStringContainsString('Run-'.$secondRun->id, $reporter->documentName($secondRun));
    }

    public function test_security_headers_are_added_and_hsts_is_only_sent_over_https(): void
    {
        $http = $this->get('/login')->assertOk();
        $http->assertHeader('X-Content-Type-Options', 'nosniff')
            ->assertHeader('X-Frame-Options', 'DENY')
            ->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin')
            ->assertHeader('Permissions-Policy', 'camera=(), microphone=(), geolocation=()')
            ->assertHeaderMissing('Strict-Transport-Security');
        $this->assertStringContainsString("frame-ancestors 'none'", (string) $http->headers->get('Content-Security-Policy'));

        $this->get('https://localhost/login')
            ->assertHeader('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
    }

    public function test_primary_controls_have_accessible_names(): void
    {
        $this->seed();
        $owner = User::where('role', 'owner')->firstOrFail();

        $this->actingAs($owner)->get(route('datasets.index'))
            ->assertOk()
            ->assertSee('aria-label="Stok akhir Gula Pasir 1kg"', false)
            ->assertSee('aria-labelledby="import-dataset-title"', false)
            ->assertSee('aria-label="Tutup dialog impor laporan"', false);
        $this->actingAs($owner)->get(route('products.index'))
            ->assertOk()
            ->assertSee('for="product-search"', false);
    }

    public function test_login_does_not_expose_or_autofill_seed_credentials(): void
    {
        $response = $this->get(route('login'));

        $response->assertOk()
            ->assertDontSee('Isi akun Owner')
            ->assertDontSee('data-fill-owner', false);
    }

    public function test_historical_result_falls_back_to_its_immutable_stock_snapshot(): void
    {
        $this->seed();
        $owner = User::where('role', 'owner')->firstOrFail();
        $run = MooraRun::firstOrFail();
        $result = $run->results()->orderBy('rank_system')->firstOrFail();
        $stockCode = collect($run->criteria_snapshot)->firstWhere('source', 'ending_stock')['code'];
        $stockAtCalculation = (float) $result->raw_values[$stockCode];

        $result->update(['restock_basis' => null]);

        $this->actingAs($owner)->get(route('calculations.results', $run))
            ->assertOk()
            ->assertSee(number_format((float) $result->yi_system, 6, ',', '.'));
        $this->assertSame($stockAtCalculation, $result->onHandAtCalculation($run->criteria_snapshot));
    }

    public function test_pdf_download_is_tagged_when_chrome_is_available_or_falls_back_safely(): void
    {
        $this->seed();
        $run = MooraRun::with(['period', 'executor', 'results.product'])->firstOrFail();
        $report = Report::where('moora_run_id', $run->id)->firstOrFail();
        $reporter = app(MooraReportService::class);
        if (! $reporter->isSupported()) {
            $this->markTestSkipped('Chrome/Chromium tidak tersedia untuk pengujian tagged PDF.');
        }

        $response = $this->actingAs(User::where('role', 'owner')->firstOrFail())
            ->get(route('reports.download', $run))
            ->assertOk()
            ->assertDownload($report->document_name);
        $path = $response->baseResponse->getFile()->getPathname();
        try {
            $contents = file_get_contents($path);
            $this->assertNotFalse($contents);
            $this->assertStringStartsWith('%PDF', $contents);
            if (str_contains($contents, '/StructTreeRoot')) {
                $this->assertStringContainsString('/Marked true', $contents);
                $this->assertStringContainsString('/S /Table', $contents);
            }
        } finally {
            @unlink($path);
        }
    }
}
