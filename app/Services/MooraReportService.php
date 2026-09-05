<?php

namespace App\Services;

use App\Models\MooraRun;
use App\Models\Report;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Symfony\Component\Process\ExecutableFinder;
use Symfony\Component\Process\Process;
use Throwable;

class MooraReportService
{
    public function documentName(MooraRun $run): string
    {
        return sprintf(
            'Laporan_Restock_MOORA_%s_Run-%d_%s.pdf',
            $run->period->start_date->format('Ymd'),
            $run->id,
            $run->created_at->format('Ymd_His'),
        );
    }

    public function isSupported(): bool
    {
        return class_exists(Pdf::class);
    }

    public function hasArchive(Report $report): bool
    {
        return is_string($report->storage_path)
            && $report->storage_path !== ''
            && Storage::disk('local')->exists($report->storage_path);
    }

    public function archive(MooraRun $run, Report $report): Report
    {
        if ($this->hasArchive($report)) {
            return $report;
        }
        $temporaryPdf = $this->generate($run, $report);
        $storagePath = 'reports/'.$run->id.'/'.$report->document_name;

        try {
            if (! Storage::disk('local')->put($storagePath, File::get($temporaryPdf))) {
                throw new RuntimeException('Arsip PDF tidak dapat disimpan.');
            }

            $hash = hash_file('sha256', $temporaryPdf);
            if ($hash === false) {
                Storage::disk('local')->delete($storagePath);
                throw new RuntimeException('Checksum PDF tidak dapat dibuat.');
            }

            $report->update([
                'storage_path' => $storagePath,
                'sha256' => $hash,
                'file_size' => File::size($temporaryPdf),
                'archived_at' => now(),
            ]);

            return $report->refresh();
        } finally {
            File::delete($temporaryPdf);
        }
    }

    public function archivedPath(Report $report): string
    {
        if (! $this->hasArchive($report)) {
            throw new RuntimeException('Arsip PDF belum tersedia.');
        }

        return Storage::disk('local')->path($report->storage_path);
    }

    public function generate(MooraRun $run, Report $report): string
    {
        $directory = rtrim(sys_get_temp_dir(), DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR.'h2-asia-moora-pdf';
        File::ensureDirectoryExists($directory);
        $temporaryBase = tempnam($directory, 'moora-');
        if ($temporaryBase === false) {
            throw new RuntimeException('Berkas sementara laporan tidak dapat dibuat.');
        }
        $htmlPath = $temporaryBase.'.html';
        $profilePath = $temporaryBase.'.profile';
        File::delete($temporaryBase);
        $pdfPath = $htmlPath.'.pdf';

        try {
            if (config('reports.engine') !== 'chrome') {
                return $this->generateWithDompdf($run, $report, $pdfPath);
            }

            $binary = $this->browserBinary();
            if ($binary === null) {
                return $this->generateWithDompdf($run, $report, $pdfPath);
            }

            File::put($htmlPath, view('reports.pdf.moora', compact('run', 'report'))->render());
            File::ensureDirectoryExists($profilePath);
            $process = new Process([
                $binary,
                '--headless=new',
                '--disable-gpu',
                '--disable-dev-shm-usage',
                '--disable-extensions',
                '--no-first-run',
                '--no-default-browser-check',
                '--user-data-dir='.$profilePath,
                '--no-pdf-header-footer',
                '--generate-pdf-document-outline',
                '--print-to-pdf='.$pdfPath,
                $this->fileUrl($htmlPath),
            ]);
            $process->setTimeout((float) config('reports.render_timeout', 45));
            $process->run();

            if (! $process->isSuccessful() || ! File::exists($pdfPath) || File::size($pdfPath) === 0) {
                throw new RuntimeException('PDF laporan gagal dibuat. '.$process->getErrorOutput());
            }

            return $pdfPath;
        } catch (Throwable $exception) {
            File::delete($pdfPath);
            report($exception);

            try {
                return $this->generateWithDompdf($run, $report, $pdfPath);
            } catch (Throwable $fallbackException) {
                report($fallbackException);
            }

            throw new RuntimeException('PDF laporan gagal dibuat. Silakan coba kembali atau hubungi administrator.', 0, $exception);
        } finally {
            File::delete($htmlPath);
            File::deleteDirectory($profilePath);
        }
    }

    private function generateWithDompdf(MooraRun $run, Report $report, string $pdfPath): string
    {
        Pdf::setOption([
            'defaultFont' => 'DejaVu Sans',
            'isRemoteEnabled' => false,
            'isHtml5ParserEnabled' => true,
        ])->loadView('reports.pdf.moora', compact('run', 'report'))
            ->setPaper('a4', 'landscape')
            ->save($pdfPath);

        if (! File::exists($pdfPath) || File::size($pdfPath) === 0) {
            throw new RuntimeException('PDF laporan tidak menghasilkan berkas yang valid.');
        }

        return $pdfPath;
    }

    private function fileUrl(string $path): string
    {
        return 'file://'.str_replace('%2F', '/', rawurlencode($path));
    }

    private function browserBinary(): ?string
    {
        $configured = config('reports.chrome_binary');
        if (is_string($configured) && $configured !== '' && is_executable($configured)) {
            return $configured;
        }

        $macChrome = '/Applications/Google Chrome.app/Contents/MacOS/Google Chrome';
        if (is_executable($macChrome)) {
            return $macChrome;
        }

        $finder = new ExecutableFinder;
        foreach (['google-chrome-stable', 'google-chrome', 'chromium', 'chromium-browser', 'microsoft-edge'] as $name) {
            $binary = $finder->find($name);
            if ($binary !== null) {
                return $binary;
            }
        }

        return null;
    }
}
