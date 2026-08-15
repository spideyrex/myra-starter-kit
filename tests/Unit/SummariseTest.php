<?php

namespace Tests\Unit;

use App\Admin\Traits\SearchableQuery;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Tests\TestCase;

class SummariseTest extends TestCase
{
    use SearchableQuery;

    protected function setUp(): void
    {
        parent::setUp();

        Schema::create('summary_widgets', function (Blueprint $table) {
            $table->id();
            $table->integer('stock');
            $table->decimal('price', 8, 2);
        });

        foreach ([[3, 10.00], [1, 20.50], [2, 30.25]] as [$stock, $price]) {
            SummaryWidget::create(['stock' => $stock, 'price' => $price]);
        }
    }

    protected function tearDown(): void
    {
        Schema::dropIfExists('summary_widgets');
        parent::tearDown();
    }

    public function test_it_aggregates_each_summary_type(): void
    {
        $out = $this->summarise(SummaryWidget::query(), [
            'stock' => 'min',
        ]);

        $this->assertSame(1, (int) $out['stock']);

        $out = $this->summarise(SummaryWidget::query(), [
            'stock' => 'max',
            'price' => 'sum',
        ]);

        $this->assertSame(3, (int) $out['stock']);
        $this->assertEqualsWithDelta(60.75, $out['price'], 0.001);
    }

    public function test_it_counts_and_averages(): void
    {
        $out = $this->summarise(SummaryWidget::query(), [
            'stock' => 'count',
            'price' => 'average',
        ]);

        $this->assertSame(3, $out['stock']);
        $this->assertEqualsWithDelta(20.25, $out['price'], 0.001);
    }

    public function test_range_reports_min_and_max(): void
    {
        $out = $this->summarise(SummaryWidget::query(), ['stock' => 'range']);

        $this->assertSame('1 – 3', $out['stock']);
    }

    public function test_unknown_type_is_null(): void
    {
        $out = $this->summarise(SummaryWidget::query(), ['stock' => 'bogus']);

        $this->assertNull($out['stock']);
    }
}

class SummaryWidget extends Model
{
    public $timestamps = false;

    protected $table = 'summary_widgets';

    protected $fillable = ['stock', 'price'];
}
