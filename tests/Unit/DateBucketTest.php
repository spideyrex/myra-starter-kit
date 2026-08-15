<?php

namespace Tests\Unit;

use App\Admin\Report\Bucket;
use App\Admin\Report\Period;
use App\Support\DateBucket;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class DateBucketTest extends TestCase
{
    public function test_sqlite_expressions(): void
    {
        $this->assertSame("strftime('%Y-%m-%d %H:00', created_at)", DateBucket::sql(Bucket::Hour, 'created_at', 'sqlite'));
        $this->assertSame("strftime('%Y-%m-%d', created_at)", DateBucket::sql(Bucket::Day, 'created_at', 'sqlite'));
        $this->assertSame("strftime('%Y-W%W', created_at)", DateBucket::sql(Bucket::Week, 'created_at', 'sqlite'));
        $this->assertSame("strftime('%Y-%m', created_at)", DateBucket::sql(Bucket::Month, 'created_at', 'sqlite'));
        $this->assertSame(
            "strftime('%Y', created_at) || '-Q' || ((cast(strftime('%m', created_at) as integer)+2)/3)",
            DateBucket::sql(Bucket::Quarter, 'created_at', 'sqlite'),
        );
        $this->assertSame("strftime('%Y', created_at)", DateBucket::sql(Bucket::Year, 'created_at', 'sqlite'));
    }

    public function test_pgsql_expressions(): void
    {
        $this->assertSame("to_char(created_at, 'YYYY-MM-DD HH24:00')", DateBucket::sql(Bucket::Hour, 'created_at', 'pgsql'));
        $this->assertSame("to_char(created_at, 'YYYY-MM-DD')", DateBucket::sql(Bucket::Day, 'created_at', 'pgsql'));
        $this->assertSame('to_char(created_at, \'IYYY-"W"IW\')', DateBucket::sql(Bucket::Week, 'created_at', 'pgsql'));
        $this->assertSame("to_char(created_at, 'YYYY-MM')", DateBucket::sql(Bucket::Month, 'created_at', 'pgsql'));
        $this->assertSame('to_char(created_at, \'YYYY-"Q"Q\')', DateBucket::sql(Bucket::Quarter, 'created_at', 'pgsql'));
        $this->assertSame("to_char(created_at, 'YYYY')", DateBucket::sql(Bucket::Year, 'created_at', 'pgsql'));
    }

    public function test_mysql_expressions(): void
    {
        $this->assertSame("date_format(created_at, '%Y-%m-%d %H:00')", DateBucket::sql(Bucket::Hour, 'created_at', 'mysql'));
        $this->assertSame("date_format(created_at, '%Y-%m-%d')", DateBucket::sql(Bucket::Day, 'created_at', 'mysql'));
        $this->assertSame("date_format(created_at, '%x-W%v')", DateBucket::sql(Bucket::Week, 'created_at', 'mysql'));
        $this->assertSame("date_format(created_at, '%Y-%m')", DateBucket::sql(Bucket::Month, 'created_at', 'mysql'));
        $this->assertSame("concat(date_format(created_at, '%Y'), '-Q', quarter(created_at))", DateBucket::sql(Bucket::Quarter, 'created_at', 'mysql'));
        $this->assertSame("date_format(created_at, '%Y')", DateBucket::sql(Bucket::Year, 'created_at', 'mysql'));
    }

    public function test_sql_rejects_an_injected_identifier(): void
    {
        $this->expectException(InvalidArgumentException::class);

        DateBucket::sql(Bucket::Day, 'created_at); drop table users;--', 'sqlite');
    }

    public function test_day_series_is_gap_filled_and_ascending(): void
    {
        $period = Period::between('2026-01-01', '2026-01-31');
        $series = DateBucket::series(Bucket::Day, $period, 'sqlite');

        $this->assertCount(31, $series);
        $this->assertSame('2026-01-01', $series[0]);
        $this->assertSame('2026-01-31', $series[30]);
        $sorted = $series;
        sort($sorted);
        $this->assertSame($sorted, $series);
    }

    public function test_month_series_crosses_a_year_boundary(): void
    {
        $period = Period::between('2025-12-01', '2026-01-31');

        $this->assertSame(['2025-12', '2026-01'], DateBucket::series(Bucket::Month, $period, 'sqlite'));
    }

    public function test_quarter_keys_match_the_documented_format(): void
    {
        $period = Period::between('2026-01-01', '2026-12-31');

        $this->assertSame(['2026-Q1', '2026-Q2', '2026-Q3', '2026-Q4'], DateBucket::series(Bucket::Quarter, $period, 'sqlite'));
    }

    public function test_label_formats_a_month_key(): void
    {
        $this->assertSame('Aug 2026', DateBucket::label(Bucket::Month, '2026-08'));
    }
}
