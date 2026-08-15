<?php

namespace App\Admin\Export;

use App\Support\Csv;
use RuntimeException;
use ZipArchive;

/**
 * Minimal streaming XLSX writer. Rows are appended to a temp sheet stream as
 * inline strings (no shared-strings table), so memory stays O(1) regardless of
 * row count; the finished sheet is zipped and piped to the response.
 *
 * Deliberately dependency-free: the sweep must not require a composer.lock
 * change to build. Swap the body for OpenSpout later without touching callers.
 */
final class XlsxRowWriter implements RowWriter
{
    /** @var resource|null */
    private $sheet = null;

    private string $sheetPath = '';

    private int $rowIndex = 0;

    public function __construct(private readonly string $sheetName = 'Sheet1') {}

    public static function isSupported(): bool
    {
        return class_exists(ZipArchive::class);
    }

    public function open(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'myra-xlsx-');

        if ($path === false) {
            throw new RuntimeException('Unable to allocate a temporary file for the XLSX export.');
        }

        $this->sheetPath = $path;
        $this->sheet = fopen($this->sheetPath, 'w');

        fwrite($this->sheet, '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><sheetData>');
    }

    public function header(array $labels): void
    {
        $this->row($labels);
    }

    public function row(array $values): void
    {
        $this->rowIndex++;
        $xml = '<row r="' . $this->rowIndex . '">';
        $i = 0;

        foreach ($values as $value) {
            $ref = self::columnLetter($i++) . $this->rowIndex;
            $cell = self::xmlSafe(Csv::cell($value));

            if ($cell === '') {
                continue;
            }

            $xml .= '<c r="' . $ref . '" t="inlineStr"><is><t xml:space="preserve">'
                . htmlspecialchars($cell, ENT_QUOTES | ENT_XML1, 'UTF-8')
                . '</t></is></c>';
        }

        fwrite($this->sheet, $xml . '</row>');
    }

    public function flush(): void
    {
        if (is_resource($this->sheet)) {
            fflush($this->sheet);
        }
    }

    public function close(): void
    {
        if (! is_resource($this->sheet)) {
            return;
        }

        fwrite($this->sheet, '</sheetData></worksheet>');
        fclose($this->sheet);
        $this->sheet = null;

        $zipPath = tempnam(sys_get_temp_dir(), 'myra-xlsxz-');
        $zip = new ZipArchive;
        $zip->open($zipPath, ZipArchive::OVERWRITE | ZipArchive::CREATE);
        $zip->addFromString('[Content_Types].xml', $this->contentTypes());
        $zip->addFromString('_rels/.rels', $this->rootRels());
        $zip->addFromString('xl/workbook.xml', $this->workbook());
        $zip->addFromString('xl/_rels/workbook.xml.rels', $this->workbookRels());
        $zip->addFile($this->sheetPath, 'xl/worksheets/sheet1.xml');
        $zip->close();

        readfile($zipPath);

        @unlink($zipPath);
        @unlink($this->sheetPath);
    }

    private function contentTypes(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
            . '<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
            . '<Default Extension="xml" ContentType="application/xml"/>'
            . '<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>'
            . '<Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>'
            . '</Types>';
    }

    private function rootRels(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>'
            . '</Relationships>';
    }

    private function workbook(): string
    {
        $name = htmlspecialchars(substr(preg_replace('/[\\\\\/\?\*\[\]:]/', '', $this->sheetName) ?: 'Sheet1', 0, 31), ENT_QUOTES | ENT_XML1, 'UTF-8');

        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"'
            . ' xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
            . '<sheets><sheet name="' . $name . '" sheetId="1" r:id="rId1"/></sheets></workbook>';
    }

    private function workbookRels(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/>'
            . '</Relationships>';
    }

    /** Strip control characters XML 1.0 cannot represent. */
    private static function xmlSafe(string $value): string
    {
        return (string) preg_replace('/[^\x{9}\x{A}\x{D}\x{20}-\x{D7FF}\x{E000}-\x{FFFD}]/u', '', $value);
    }

    private static function columnLetter(int $index): string
    {
        $letters = '';

        for ($i = $index; $i >= 0; $i = intdiv($i, 26) - 1) {
            $letters = chr(65 + ($i % 26)) . $letters;
        }

        return $letters;
    }
}
