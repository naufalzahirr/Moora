<?php

namespace App\Http\Controllers;

use App\Models\MooraRun;
use App\Models\Report;
use App\Services\ActivityLogger;
use App\Services\MooraReportService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

class ReportController extends Controller
{
    public function index(Request $request): View
    {
        $search = trim($request->string('q')->toString());
        $from = $request->string('from')->toString();
        $until = $request->string('until')->toString();
        $runsQuery = MooraRun::with(['period', 'report'])->latest('id');
        if ($search !== '') {
            $runsQuery->whereHas('period', fn ($query) => $query->where('name', 'like', "%{$search}%"));
        }
        if ($from !== '') {
            $runsQuery->whereHas('period', fn ($query) => $query->whereDate('end_date', '>=', $from));
        }
        if ($until !== '') {
            $runsQuery->whereHas('period', fn ($query) => $query->whereDate('start_date', '<=', $until));
        }
        $runs = $runsQuery->paginate(10)->withQueryString();
        $run = $request->integer('run')
            ? MooraRun::with(['period', 'results' => fn ($query) => $query->with('product')->orderBy('rank_system'), 'report'])->findOrFail($request->integer('run'))
            : MooraRun::with(['period', 'results' => fn ($query) => $query->with('product')->orderBy('rank_system'), 'report'])->latest('id')->first();

        return view('reports.index', compact('runs', 'run', 'search', 'from', 'until'));
    }

    public function store(Request $request, MooraRun $run, ActivityLogger $logger, MooraReportService $reporter): RedirectResponse
    {
        $run->load(['period', 'executor', 'results' => fn ($query) => $query->with('product')->orderBy('rank_system')]);
        $report = Report::firstOrCreate(
            ['moora_run_id' => $run->id],
            [
                'document_name' => $reporter->documentName($run),
                'generated_by' => $request->user()->id,
                'generated_at' => now(),
            ]
        );
        try {
            $report = $reporter->archive($run, $report);
        } catch (Throwable $exception) {
            $logger->log($request->user(), 'report.archive_failed', 'Gagal mengarsipkan laporan.', ['run_id' => $run->id]);
            report($exception);

            return redirect()->route('reports.index', ['run' => $run])->withErrors([
                'report' => 'Laporan belum dapat diarsipkan. Silakan coba kembali; detail kegagalan telah dicatat.',
            ]);
        }

        $logger->log($request->user(), 'report.saved', "Mengarsipkan laporan {$report->document_name}.", [
            'run_id' => $run->id,
            'sha256' => $report->sha256,
        ]);

        return redirect()->route('reports.index', ['run' => $run])->with('success', 'Laporan PDF berhasil diarsipkan ke riwayat.');
    }

    public function download(Request $request, MooraRun $run, ActivityLogger $logger, MooraReportService $reporter): Response
    {
        $run->load(['period', 'executor', 'results' => fn ($query) => $query->with('product')->orderBy('rank_system')]);
        $report = Report::firstOrCreate(
            ['moora_run_id' => $run->id],
            [
                'document_name' => $reporter->documentName($run),
                'generated_by' => $request->user()->id,
                'generated_at' => now(),
            ]
        );

        try {
            $report = $reporter->archive($run, $report);
        } catch (Throwable $exception) {
            $logger->log($request->user(), 'report.download_failed', 'Gagal membuat atau membaca arsip PDF.', ['run_id' => $run->id]);
            report($exception);

            return redirect()->route('reports.index', ['run' => $run])->withErrors([
                'report' => 'PDF belum dapat dibuat saat ini. Silakan coba kembali; detail kegagalan telah dicatat.',
            ]);
        }

        $logger->log($request->user(), 'report.downloaded', "Mengunduh laporan {$report->document_name}.", [
            'run_id' => $run->id,
            'sha256' => $report->sha256,
        ]);

        return response()->download($reporter->archivedPath($report), $report->document_name, [
            'Content-Type' => 'application/pdf',
        ]);
    }
}
