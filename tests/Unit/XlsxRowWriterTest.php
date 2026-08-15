<?php

namespace Tests\Unit;

use App\Admin\Export\XlsxRowWriter;
use PHPUnit\Framework\TestCase;
use ZipArchive;

class XlsxRowWriterTest extends TestCase
{
    private function sheetXml(array $rows): string
    {
        if (! XlsxRowWriter::isSupported()) {
            $this->markTestSkipped('ext-zip is not available.');
        }

        $writer = new XlsxRowWriter('Sheet1');

        ob_start();
        $writer->open();
        foreach ($rows as $row) {
            $writer->row($row);
        }
        $writer->close();
        $binary = ob_get_clean();

        $path = tempnam(sys_get_temp_dir(), 'myra-test-');
        file_put_contents($path, $binary);

        $zip = new ZipArchive;
        $this->assertTrue($zip->open($path) === true, 'writer did not emit a readable zip');
        $xml = $zip->getFromName('xl/worksheets/sheet1.xml');
        $zip->close();
        @unlink($path);

        return (string) $xml;
    }

    public function test_formula_injection_is_neutralised_in_xlsx_too(): void
    {
        $xml = $this->sheetXml([['Name', 'Payload'], ['Ada', '=cmd|/c calc']]);

        // Csv::cell() prefixes the quote; htmlspecialchars encodes it.
        $this->assertMatchesRegularExpression('/(&#0?39;|&apos;|\')=cmd\|\/c calc/', $xml);
        $this->assertStringNotContainsString('<t xml:space="preserve">=cmd', $xml);
    }

    public function test_markup_in_a_value_is_escaped_not_injected(): void
    {
        $xml = $this->sheetXml([['<is><t>boom</t></is>']]);

        $this->assertStringContainsString('&lt;is&gt;&lt;t&gt;boom', $xml);
    }

    public function test_control_characters_are_stripped(): void
    {
        $xml = $this->sheetXml([["ok\x00bad"]]);

        $this->assertStringContainsString('okbad', $xml);
    }
}
