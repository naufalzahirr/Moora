<?php

namespace App\Services;

use DOMDocument;
use DOMElement;
use DOMXPath;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\ValidationException;
use PhpOffice\PhpSpreadsheet\Reader\Xls;
use RuntimeException;
use SimpleXMLElement;
use Throwable;
use ZipArchive;

class SalesImportService
{
    /** @return array<int, array{code: string, name: string, sold_quantity: float, sales_value: float, ending_stock?: float|null}> */
    public function parse(UploadedFile $file): array
    {
        $extension = strtolower($file->getClientOriginalExtension());
        $rows = match ($extension) {
            'csv' => $this->parseDelimited($file->getRealPath()),
            'xlsx' => $this->parseXlsx($file->getRealPath()),
            'xls' => $this->parseLegacyXls($file->getRealPath()),
            default => throw ValidationException::withMessages(['file' => 'Format berkas harus CSV, XLS, atau XLSX.']),
        };

        return $this->normalizeRows($rows);
    }

    /** @return array<int, array<int, string>> */
    private function parseDelimited(string $path): array
    {
        $handle = fopen($path, 'rb');
        if ($handle === false) {
            throw new RuntimeException('Berkas tidak dapat dibaca.');
        }

        $firstLine = fgets($handle) ?: '';
        rewind($handle);
        $delimiters = [',' => substr_count($firstLine, ','), ';' => substr_count($firstLine, ';'), "\t" => substr_count($firstLine, "\t")];
        arsort($delimiters);
        $delimiter = (string) array_key_first($delimiters);
        $rows = [];

        while (($row = fgetcsv($handle, 0, $delimiter)) !== false) {
            $rows[] = array_map(fn ($value): string => trim((string) $value), $row);
            if (count($rows) > 10000) {
                fclose($handle);
                throw ValidationException::withMessages(['file' => 'CSV melebihi batas 10.000 baris. Pecah berkas menjadi beberapa impor agar dapat ditinjau dengan aman.']);
            }
        }
        fclose($handle);

        return $rows;
    }

    /** @return array<int, array<int, string>> */
    private function parseXlsx(string $path): array
    {
        $zip = new ZipArchive;
        if ($zip->open($path) !== true) {
            throw ValidationException::withMessages(['file' => 'Berkas XLSX tidak dapat dibuka.']);
        }

        try {
            $this->validateArchiveSize($zip);

            $workbook = $this->xlsxXml($zip, 'xl/workbook.xml', 'Struktur workbook XLSX tidak ditemukan.');
            $relationships = $this->xlsxXml($zip, 'xl/_rels/workbook.xml.rels', 'Relasi workbook XLSX tidak ditemukan.');
            $workbookXPath = new DOMXPath($workbook);
            $firstSheet = $workbookXPath->query('/*[local-name()="workbook"]/*[local-name()="sheets"]/*[local-name()="sheet"][1]')->item(0);

            if (! $firstSheet instanceof DOMElement) {
                throw ValidationException::withMessages(['file' => 'Workbook XLSX tidak memiliki sheet.']);
            }

            $relationshipId = $firstSheet->getAttributeNS(
                'http://schemas.openxmlformats.org/officeDocument/2006/relationships',
                'id'
            ) ?: $firstSheet->getAttribute('r:id');
            if ($relationshipId === '') {
                throw ValidationException::withMessages(['file' => 'Relasi sheet pertama pada XLSX tidak valid.']);
            }
            $relationshipsXPath = new DOMXPath($relationships);
            $relationship = null;
            foreach ($relationshipsXPath->query('/*[local-name()="Relationships"]/*[local-name()="Relationship"]') ?: [] as $candidate) {
                if ($candidate instanceof DOMElement && hash_equals($candidate->getAttribute('Id'), $relationshipId)) {
                    $relationship = $candidate;
                    break;
                }
            }

            if (! $relationship instanceof DOMElement || strtolower($relationship->getAttribute('TargetMode')) === 'external') {
                throw ValidationException::withMessages(['file' => 'Sheet pertama pada XLSX tidak dapat ditemukan.']);
            }

            $sheetPath = $this->xlsxTargetPath($relationship->getAttribute('Target'));
            $sheet = $this->xlsxXml($zip, $sheetPath, 'Sheet pertama pada XLSX tidak ditemukan.');
            $sharedStrings = $this->sharedStrings($zip);
            $sheetXPath = new DOMXPath($sheet);
            $xmlRows = $sheetXPath->query('/*[local-name()="worksheet"]/*[local-name()="sheetData"]/*[local-name()="row"]');

            if ($xmlRows === false) {
                throw ValidationException::withMessages(['file' => 'Data pada sheet pertama XLSX tidak valid.']);
            }

            $rows = [];
            foreach ($xmlRows as $xmlRow) {
                if (! $xmlRow instanceof DOMElement) {
                    continue;
                }

                $row = [];
                $nextColumn = 0;
                foreach ($sheetXPath->query('./*[local-name()="c"]', $xmlRow) ?: [] as $cell) {
                    if (! $cell instanceof DOMElement) {
                        continue;
                    }

                    $reference = strtoupper($cell->getAttribute('r'));
                    $column = preg_match('/^([A-Z]+)/', $reference, $match)
                        ? $this->columnIndex($match[1])
                        : $nextColumn;
                    $row[$column] = trim($this->xlsxCellValue($sheetXPath, $cell, $sharedStrings));
                    $nextColumn = $column + 1;
                }

                if ($row !== []) {
                    ksort($row);
                    $lastColumn = (int) array_key_last($row);
                    $rows[] = array_map(fn (int $column): string => $row[$column] ?? '', range(0, $lastColumn));
                }

                if (count($rows) > 10000) {
                    throw ValidationException::withMessages(['file' => 'XLSX melebihi batas 10.000 baris.']);
                }
            }

            return $rows;
        } catch (ValidationException $exception) {
            throw $exception;
        } catch (Throwable $exception) {
            report($exception);

            throw ValidationException::withMessages([
                'file' => 'Berkas XLSX gagal dibaca. Pastikan berkas tidak rusak dan disimpan dalam format XLSX standar.',
            ]);
        } finally {
            $zip->close();
        }
    }

    /** @return array<int, array<int, string>> */
    private function parseLegacyXls(string $path): array
    {
        $contents = file_get_contents($path);
        if ($contents === false) {
            throw ValidationException::withMessages(['file' => 'Berkas XLS tidak dapat dibaca.']);
        }

        if (str_starts_with(ltrim($contents), '<?xml') || str_contains($contents, '<Workbook')) {
            $xml = simplexml_load_string($contents, SimpleXMLElement::class, LIBXML_NONET | LIBXML_COMPACT);
            if (! $xml instanceof SimpleXMLElement) {
                throw ValidationException::withMessages(['file' => 'Struktur Spreadsheet XML tidak valid.']);
            }
            $xml->registerXPathNamespace('ss', 'urn:schemas-microsoft-com:office:spreadsheet');
            $rows = [];
            foreach ($xml->xpath('//ss:Worksheet[1]/ss:Table/ss:Row') ?: [] as $row) {
                $values = [];
                foreach ($row->xpath('./ss:Cell/ss:Data') ?: [] as $data) {
                    $values[] = trim((string) $data);
                }
                $rows[] = $values;
            }

            return $rows;
        }

        try {
            $reader = new Xls;
            $reader->setReadDataOnly(true);
            $spreadsheet = $reader->load($path);
            $sheet = $spreadsheet->getSheet(0);
            $highestRow = $sheet->getHighestDataRow();

            if ($highestRow > 10000) {
                throw ValidationException::withMessages(['file' => 'XLS melebihi batas 10.000 baris.']);
            }

            $highestColumn = $sheet->getHighestDataColumn();
            $rows = $highestRow > 0
                ? $sheet->rangeToArray("A1:{$highestColumn}{$highestRow}", null, true, false, false)
                : [];
            $spreadsheet->disconnectWorksheets();

            return array_map(
                fn (array $row): array => array_map(fn ($value): string => trim((string) $value), $row),
                $rows
            );
        } catch (ValidationException $exception) {
            throw $exception;
        } catch (Throwable $exception) {
            report($exception);

            throw ValidationException::withMessages([
                'file' => 'Berkas XLS tidak dapat dibaca. Pastikan berkas tidak rusak atau simpan kembali sebagai XLSX.',
            ]);
        }
    }

    /**
     * @param  array<int, array<int, string>>  $rows
     * @return array<int, array{code: string, name: string, sold_quantity: float, sales_value: float, ending_stock?: float|null}>
     */
    private function normalizeRows(array $rows): array
    {
        if (count($rows) < 2) {
            throw ValidationException::withMessages(['file' => 'Berkas tidak memiliki baris data.']);
        }

        $headerIndex = null;
        $map = [];
        foreach ($rows as $index => $row) {
            $candidate = $this->headerMap($row);
            if (isset($candidate['code'], $candidate['name'], $candidate['sold_quantity'], $candidate['sales_value'])) {
                $headerIndex = $index;
                $map = $candidate;
                break;
            }
        }

        if ($headerIndex === null) {
            throw ValidationException::withMessages([
                'file' => 'Kolom kode barang, nama barang, jumlah terjual, dan nilai penjualan tidak ditemukan.',
            ]);
        }

        $normalized = [];
        $errors = [];
        $seenCodes = [];
        foreach ($rows as $index => $row) {
            if ($index <= $headerIndex || collect($row)->every(fn ($value): bool => trim((string) $value) === '')) {
                continue;
            }

            $rowNumber = $index + 1;
            $code = trim((string) ($row[$map['code']] ?? ''));
            $name = trim((string) ($row[$map['name']] ?? ''));

            if ($code === '') {
                $errors[] = "Baris {$rowNumber}: kode barang wajib diisi.";
            }
            if ($name === '') {
                $errors[] = "Baris {$rowNumber}: nama barang wajib diisi.";
            }
            if ($code !== '' && isset($seenCodes[strtolower($code)])) {
                $errors[] = "Baris {$rowNumber}: kode barang {$code} muncul lebih dari sekali.";
            }

            try {
                $soldQuantity = $this->number($row[$map['sold_quantity']] ?? null, 'jumlah terjual', $rowNumber);
            } catch (RuntimeException $exception) {
                $errors[] = $exception->getMessage();
                $soldQuantity = null;
            }

            try {
                $salesValue = $this->number($row[$map['sales_value']] ?? null, 'nilai penjualan', $rowNumber);
            } catch (RuntimeException $exception) {
                $errors[] = $exception->getMessage();
                $salesValue = null;
            }

            $endingStock = null;
            if (isset($map['ending_stock']) && trim((string) ($row[$map['ending_stock']] ?? '')) !== '') {
                try {
                    $endingStock = $this->number($row[$map['ending_stock']], 'stok akhir', $rowNumber);
                } catch (RuntimeException $exception) {
                    $errors[] = $exception->getMessage();
                }
            }

            if ($code === '' || $name === '' || $soldQuantity === null || $salesValue === null || isset($seenCodes[strtolower($code)])) {
                continue;
            }

            $seenCodes[strtolower($code)] = true;

            $normalized[] = [
                'code' => $code,
                'name' => $name,
                'sold_quantity' => $soldQuantity,
                'sales_value' => $salesValue,
                ...(isset($map['ending_stock']) ? ['ending_stock' => $endingStock] : []),
            ];
        }

        if ($errors !== []) {
            throw ValidationException::withMessages(['file' => $errors]);
        }

        if ($normalized === []) {
            throw ValidationException::withMessages(['file' => 'Tidak ada baris data barang yang valid.']);
        }

        return $normalized;
    }

    /** @return array<string, int> */
    private function headerMap(array $row): array
    {
        $aliases = [
            'code' => ['kode barang', 'no barang', 'no. barang', 'kode', 'code'],
            'name' => ['nama barang', 'deskripsi barang', 'barang', 'description'],
            'sold_quantity' => ['jumlah terjual', 'kts standar', 'kts. standar', 'terjual', 'qty'],
            'ending_stock' => ['stok akhir', 'stock akhir', 'ending stock', 'sisa stok'],
            'sales_value' => ['nilai penjualan', 'nilai barang', 'omzet', 'sales value'],
        ];
        $map = [];
        foreach ($row as $index => $heading) {
            $heading = strtolower(trim(preg_replace('/\s+/', ' ', ltrim(str_replace('_', ' ', (string) $heading), "\xEF\xBB\xBF"))));
            foreach ($aliases as $field => $values) {
                if (in_array($heading, $values, true)) {
                    $map[$field] = $index;
                }
            }
        }

        return $map;
    }

    private function number(mixed $value, string $column, int $row): float
    {
        if (is_int($value) || is_float($value)) {
            $number = (float) $value;
            if (! is_finite($number) || $number < 0) {
                throw new RuntimeException("Baris {$row}: {$column} wajib berupa angka nonnegatif.");
            }

            return $number;
        }

        $original = trim((string) $value);
        if ($original === '') {
            throw new RuntimeException("Baris {$row}: {$column} wajib diisi.");
        }

        $value = preg_replace('/^(?:rp\.?|idr)\s*/i', '', $original) ?? '';
        $value = str_replace(["\u{00A0}", ' '], '', $value);
        if ($value === '' || ! preg_match('/^[+-]?(?:\d+(?:[.,]\d+)*|[.,]\d+)$/', $value)) {
            throw new RuntimeException("Baris {$row}: {$column} '{$original}' bukan angka yang valid.");
        }

        $sign = str_starts_with($value, '-') ? '-' : '';
        $value = ltrim($value, '+-');
        $commaPosition = strrpos($value, ',');
        $dotPosition = strrpos($value, '.');

        if (str_contains($value, ',') && str_contains($value, '.')) {
            $value = $commaPosition > $dotPosition
                ? str_replace(['.', ','], ['', '.'], $value)
                : str_replace(',', '', $value);
        } elseif (str_contains($value, '.') || str_contains($value, ',')) {
            $separator = str_contains($value, ',') ? ',' : '.';
            $parts = explode($separator, $value);
            $isThousands = count($parts) > 2
                ? collect(array_slice($parts, 1))->every(fn (string $part): bool => strlen($part) === 3)
                : strlen($parts[1] ?? '') === 3 && ($parts[0] ?? '') !== '0';

            if ($isThousands) {
                $value = implode('', $parts);
            } else {
                $decimal = array_pop($parts);
                $value = implode('', $parts).'.'.$decimal;
            }
        }

        $number = (float) ($sign.$value);
        if (! is_finite($number) || $number < 0) {
            throw new RuntimeException("Baris {$row}: {$column} wajib berupa angka nonnegatif.");
        }

        return $number;
    }

    private function validateArchiveSize(ZipArchive $zip): void
    {
        if ($zip->numFiles > 1000) {
            throw ValidationException::withMessages(['file' => 'XLSX memiliki terlalu banyak bagian untuk diproses dengan aman.']);
        }

        $totalSize = 0;
        for ($index = 0; $index < $zip->numFiles; $index++) {
            $stat = $zip->statIndex($index);
            $size = (int) ($stat['size'] ?? 0);
            $totalSize += $size;
            if ($size > 20 * 1024 * 1024 || $totalSize > 50 * 1024 * 1024) {
                throw ValidationException::withMessages(['file' => 'Isi XLSX terlalu besar untuk diproses dengan aman.']);
            }
        }
    }

    private function xlsxXml(ZipArchive $zip, string $entry, string $missingMessage): DOMDocument
    {
        $contents = $zip->getFromName($entry);
        if ($contents === false) {
            throw ValidationException::withMessages(['file' => $missingMessage]);
        }

        $previous = libxml_use_internal_errors(true);
        try {
            $document = new DOMDocument;
            if (! $document->loadXML($contents, LIBXML_NONET | LIBXML_COMPACT)) {
                throw ValidationException::withMessages(['file' => 'Struktur XML pada XLSX tidak valid.']);
            }

            return $document;
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
        }
    }

    /** @return array<int, string> */
    private function sharedStrings(ZipArchive $zip): array
    {
        if ($zip->locateName('xl/sharedStrings.xml') === false) {
            return [];
        }

        $document = $this->xlsxXml($zip, 'xl/sharedStrings.xml', 'Shared strings XLSX tidak dapat dibaca.');
        $xpath = new DOMXPath($document);
        $strings = [];
        foreach ($xpath->query('/*[local-name()="sst"]/*[local-name()="si"]') ?: [] as $item) {
            $text = '';
            foreach ($xpath->query('.//*[local-name()="t"]', $item) ?: [] as $node) {
                $text .= $node->textContent;
            }
            $strings[] = $text;
        }

        return $strings;
    }

    /** @param array<int, string> $sharedStrings */
    private function xlsxCellValue(DOMXPath $xpath, DOMElement $cell, array $sharedStrings): string
    {
        $type = $cell->getAttribute('t');
        if ($type === 'inlineStr') {
            $value = '';
            foreach ($xpath->query('.//*[local-name()="is"]//*[local-name()="t"]', $cell) ?: [] as $node) {
                $value .= $node->textContent;
            }

            return $value;
        }

        $value = $xpath->query('./*[local-name()="v"]', $cell)->item(0)?->textContent ?? '';

        return $type === 's' ? ($sharedStrings[(int) $value] ?? '') : $value;
    }

    private function xlsxTargetPath(string $target): string
    {
        $target = str_replace('\\', '/', trim($target));
        $path = str_starts_with($target, '/') ? ltrim($target, '/') : 'xl/'.$target;
        $segments = [];
        foreach (explode('/', $path) as $segment) {
            if ($segment === '' || $segment === '.') {
                continue;
            }
            if ($segment === '..') {
                array_pop($segments);
            } else {
                $segments[] = $segment;
            }
        }

        $path = implode('/', $segments);
        if (! str_starts_with($path, 'xl/')) {
            throw ValidationException::withMessages(['file' => 'Lokasi sheet pada XLSX tidak valid.']);
        }

        return $path;
    }

    private function columnIndex(string $letters): int
    {
        $index = 0;
        foreach (str_split($letters) as $letter) {
            $index = ($index * 26) + (ord($letter) - 64);
        }

        return $index - 1;
    }
}
