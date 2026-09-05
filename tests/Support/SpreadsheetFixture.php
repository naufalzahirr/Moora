<?php

namespace Tests\Support;

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xls as XlsWriter;
use RuntimeException;
use ZipArchive;

class SpreadsheetFixture
{
    public static function xlsx(bool $prefixed = true, bool $emptySheet = false): string
    {
        $path = tempnam(sys_get_temp_dir(), 'h2-xlsx-');
        if ($path === false) {
            throw new RuntimeException('Temporary XLSX fixture could not be created.');
        }

        $prefix = $prefixed ? 'x:' : '';
        $namespace = $prefixed
            ? 'xmlns:x="http://schemas.openxmlformats.org/spreadsheetml/2006/main"'
            : 'xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"';
        $sheetRows = $emptySheet ? '' : <<<XML
  <{$prefix}row r="1"><{$prefix}c r="A1" t="s"><{$prefix}v>0</{$prefix}v></{$prefix}c><{$prefix}c r="B1" t="s"><{$prefix}v>1</{$prefix}v></{$prefix}c><{$prefix}c r="C1" t="s"><{$prefix}v>2</{$prefix}v></{$prefix}c><{$prefix}c r="D1" t="s"><{$prefix}v>3</{$prefix}v></{$prefix}c></{$prefix}row>
  <{$prefix}row r="2"><{$prefix}c r="A2" t="s"><{$prefix}v>4</{$prefix}v></{$prefix}c><{$prefix}c r="B2" t="s"><{$prefix}v>5</{$prefix}v></{$prefix}c><{$prefix}c r="C2"><{$prefix}v>25</{$prefix}v></{$prefix}c><{$prefix}c r="D2"><{$prefix}v>125000</{$prefix}v></{$prefix}c></{$prefix}row>
  <{$prefix}row r="3"><{$prefix}c r="A3" t="s"><{$prefix}v>6</{$prefix}v></{$prefix}c><{$prefix}c r="B3" t="inlineStr"><{$prefix}is><{$prefix}t>Barang Inline String</{$prefix}t></{$prefix}is></{$prefix}c><{$prefix}c r="C3"><{$prefix}v>10</{$prefix}v></{$prefix}c><{$prefix}c r="D3"><{$prefix}v>50000</{$prefix}v></{$prefix}c></{$prefix}row>
XML;

        $zip = new ZipArchive;
        $zip->open($path, ZipArchive::OVERWRITE);
        $zip->addFromString('[Content_Types].xml', <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">
  <Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>
  <Default Extension="xml" ContentType="application/xml"/>
  <Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>
  <Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>
  <Override PartName="/xl/sharedStrings.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sharedStrings+xml"/>
</Types>
XML);
        $zip->addFromString('_rels/.rels', <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/></Relationships>
XML);
        $zip->addFromString('xl/workbook.xml', <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<{$prefix}workbook {$namespace} xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"><{$prefix}sheets><{$prefix}sheet name="Data" sheetId="1" r:id="rId1"/></{$prefix}sheets></{$prefix}workbook>
XML);
        $zip->addFromString('xl/_rels/workbook.xml.rels', <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<r:Relationships xmlns:r="http://schemas.openxmlformats.org/package/2006/relationships"><r:Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/></r:Relationships>
XML);
        $zip->addFromString('xl/sharedStrings.xml', <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<{$prefix}sst {$namespace} count="7" uniqueCount="7"><{$prefix}si><{$prefix}t>Kode Barang</{$prefix}t></{$prefix}si><{$prefix}si><{$prefix}t>Nama Barang</{$prefix}t></{$prefix}si><{$prefix}si><{$prefix}t>Jumlah Terjual</{$prefix}t></{$prefix}si><{$prefix}si><{$prefix}t>Nilai Penjualan</{$prefix}t></{$prefix}si><{$prefix}si><{$prefix}t>QA-XLSX-001</{$prefix}t></{$prefix}si><{$prefix}si><{$prefix}r><{$prefix}t>Barang Shared</{$prefix}t></{$prefix}r><{$prefix}r><{$prefix}t xml:space="preserve"> String</{$prefix}t></{$prefix}r></{$prefix}si><{$prefix}si><{$prefix}t>QA-XLSX-002</{$prefix}t></{$prefix}si></{$prefix}sst>
XML);
        $zip->addFromString('xl/worksheets/sheet1.xml', <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<{$prefix}worksheet {$namespace}><{$prefix}sheetData>
{$sheetRows}</{$prefix}sheetData></{$prefix}worksheet>
XML);
        $zip->close();

        return $path;
    }

    public static function spreadsheetXml(): string
    {
        $path = tempnam(sys_get_temp_dir(), 'h2-xls-');
        if ($path === false) {
            throw new RuntimeException('Temporary XLS fixture could not be created.');
        }
        file_put_contents($path, <<<'XML'
<?xml version="1.0"?>
<Workbook xmlns="urn:schemas-microsoft-com:office:spreadsheet" xmlns:ss="urn:schemas-microsoft-com:office:spreadsheet"><Worksheet ss:Name="Data"><Table><Row><Cell><Data ss:Type="String">Kode Barang</Data></Cell><Cell><Data ss:Type="String">Nama Barang</Data></Cell><Cell><Data ss:Type="String">Jumlah Terjual</Data></Cell><Cell><Data ss:Type="String">Nilai Penjualan</Data></Cell></Row><Row><Cell><Data ss:Type="String">QA-XLS-001</Data></Cell><Cell><Data ss:Type="String">Barang XLS</Data></Cell><Cell><Data ss:Type="Number">8</Data></Cell><Cell><Data ss:Type="Number">40000</Data></Cell></Row></Table></Worksheet></Workbook>
XML);

        return $path;
    }

    public static function binaryXls(): string
    {
        $path = tempnam(sys_get_temp_dir(), 'h2-binary-xls-');
        if ($path === false) {
            throw new RuntimeException('Temporary XLS fixture could not be created.');
        }

        $spreadsheet = new Spreadsheet;
        $spreadsheet->getActiveSheet()->fromArray([
            ['Kode Barang', 'Nama Barang', 'Jumlah Terjual', 'Nilai Penjualan'],
            ['QA-BINARY-XLS-001', 'Barang XLS Biner', 9, 54000],
        ]);
        (new XlsWriter($spreadsheet))->save($path);
        $spreadsheet->disconnectWorksheets();

        return $path;
    }
}
