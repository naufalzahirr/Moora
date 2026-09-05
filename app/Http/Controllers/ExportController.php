<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\MooraRun;
use App\Models\Period;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ExportController extends Controller
{
    public function run(MooraRun $run): StreamedResponse
    {
        $run->load(['period', 'results' => fn ($query) => $query->with('product')->orderBy('rank_system')]);
        $criteria = collect($run->criteria_snapshot);
        $header = ['Rank', 'Alternatif', 'Kode Barang', 'Nama Barang'];
        foreach ($criteria as $criterion) {
            $header[] = $criterion['code'].' '.$criterion['name'];
        }
        $header = [...$header, 'Yi Sistem', 'Target Stok', 'Stok Tersedia', 'Pesanan Masuk', 'Saran Restock'];
        $rows = $run->results->map(function ($result) use ($criteria): array {
            $values = [$result->rank_system, $result->alternative_code, $result->displayProductCode(), $result->displayProductName()];
            foreach ($criteria as $criterion) {
                $values[] = $result->raw_values[$criterion['code']] ?? null;
            }

            return [...$values, $result->yi_system, $result->restock_target, $result->onHandAtCalculation($criteria->all()), $result->restock_basis['incoming'] ?? null, $result->restock_quantity];
        })->all();

        return $this->download('Hasil_Restock_MOORA_Run-'.$run->id.'.xlsx', 'Hasil MOORA', $header, $rows);
    }

    public function period(Period $period): StreamedResponse
    {
        $sales = $period->sales()->with('product')->orderBy('product_id')->get();
        $stocks = $period->stockMovements()->get()->keyBy('product_id');
        $rows = $sales->map(function ($sale) use ($stocks): array {
            $stock = $stocks->get($sale->product_id);

            return [
                $sale->product->code,
                $sale->product->name,
                $sale->product->unit,
                $sale->sold_quantity,
                $sale->sales_value,
                $stock?->ending_stock,
            ];
        })->all();

        return $this->download(
            'Data_Periode_'.Str::slug($period->name).'_'.$period->id.'.xlsx',
            'Data Operasional',
            ['Kode Barang', 'Nama Barang', 'Satuan', 'Jumlah Terjual', 'Nilai Penjualan', 'Stok Akhir'],
            $rows,
        );
    }

    public function activities(Request $request): StreamedResponse
    {
        $logs = ActivityLog::with('user')->latest('id')->get();
        $rows = $logs->map(fn (ActivityLog $log): array => [
            $log->created_at?->format('Y-m-d H:i:s'),
            $log->user?->name ?? 'Sistem',
            $log->action,
            $log->description,
            json_encode($log->metadata, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        ])->all();

        return $this->download('Log_Aktivitas_H2_Asia.xlsx', 'Log Aktivitas', ['Waktu', 'Pengguna', 'Aktivitas', 'Deskripsi', 'Metadata'], $rows);
    }

    /** @param array<int, string> $header
     * @param  array<int, array<int, mixed>>  $rows
     */
    private function download(string $filename, string $sheetTitle, array $header, array $rows): StreamedResponse
    {
        return response()->streamDownload(function () use ($sheetTitle, $header, $rows): void {
            $spreadsheet = new Spreadsheet;
            $sheet = $spreadsheet->getActiveSheet();
            $sheet->setTitle($sheetTitle);
            $sheet->fromArray([$header, ...$rows], null, 'A1', true);
            $sheet->getStyle('1:1')->getFont()->setBold(true);
            $sheet->freezePane('A2');
            (new Xlsx($spreadsheet))->save('php://output');
            $spreadsheet->disconnectWorksheets();
        }, $filename, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }
}
