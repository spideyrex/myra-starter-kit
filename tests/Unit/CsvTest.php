<?php

namespace Tests\Unit;

use App\Support\Csv;
use PHPUnit\Framework\TestCase;

class CsvTest extends TestCase
{
    /** @dataProvider dangerousValues */
    public function test_prefixes_formula_characters(string $value): void
    {
        $this->assertSame("'" . $value, Csv::cell($value));
    }

    public static function dangerousValues(): array
    {
        return [
            ['=SUM(A1:A2)'],
            ['+1+1'],
            ['-2+3'],
            ['@cmd'],
            ["\tTabbed"],
            ["\rCarriage"],
        ];
    }

    public function test_leaves_normal_values_untouched(): void
    {
        $this->assertSame('Alice', Csv::cell('Alice'));
        $this->assertSame('alice@example.com', Csv::cell('alice@example.com'));
        $this->assertSame('123', Csv::cell('123'));
        $this->assertSame('', Csv::cell(''));
    }

    public function test_row_escapes_each_cell(): void
    {
        $this->assertSame(
            ['Alice', "'=2+2", 'ok'],
            Csv::row(['Alice', '=2+2', 'ok']),
        );
    }
}
