<?php

namespace Tests\Unit;

use App\Admin\Export\ExportColumn;
use App\Admin\Export\ExportDefinition;
use PHPUnit\Framework\TestCase;

class ExportDefinitionTest extends TestCase
{
    private function definition(): ExportDefinition
    {
        return ExportDefinition::make('users')->columns([
            ExportColumn::make('id')->label('ID'),
            ExportColumn::make('name')->label('Name'),
            ExportColumn::make('email')->label('Email'),
            ExportColumn::make('notes')->label('Notes')->enabledByDefault(false),
        ]);
    }

    public function test_selected_intersects_and_preserves_declared_order(): void
    {
        $keys = array_map(
            fn ($c) => $c->key(),
            $this->definition()->selected(['email', 'id', 'not_a_column']),
        );

        $this->assertSame(['id', 'email'], $keys);
    }

    public function test_selected_falls_back_to_default_columns(): void
    {
        $keys = array_map(fn ($c) => $c->key(), $this->definition()->selected(null));

        $this->assertSame(['id', 'name', 'email'], $keys);
    }

    public function test_selected_never_returns_an_undeclared_column(): void
    {
        $keys = array_map(fn ($c) => $c->key(), $this->definition()->selected(['password', 'secret']));

        $this->assertNotContains('password', $keys);
        $this->assertNotContains('secret', $keys);
    }

    public function test_preserve_sort_lowers_max_rows(): void
    {
        $definition = ExportDefinition::make('users')->maxRows(50000)->preserveSort(true);

        $this->assertSame(20000, $definition->getMaxRows());
        $this->assertTrue($definition->preservesSort());

        // A later maxRows() call cannot raise it back above the cursor() ceiling.
        $this->assertSame(20000, $definition->maxRows(50000)->getMaxRows());
    }

    public function test_counts_compiles_to_exactly_one_with_count(): void
    {
        $definition = ExportDefinition::make('users')->columns([
            ExportColumn::make('roles_count')->counts('roles'),
            ExportColumn::make('role_total')->counts('roles'),
            ExportColumn::make('orders_sum_total')->sum('orders', 'total'),
        ]);

        $this->assertSame(['roles'], $definition->countRelations());
        $this->assertSame(['orders' => 'total'], $definition->sumRelations());
        $this->assertCount(2, $definition->aggregates());
    }

    public function test_formats_are_whitelisted(): void
    {
        $definition = ExportDefinition::make('users')->formats(['csv', 'pdf', 'xlsx']);

        $this->assertSame(['csv', 'xlsx'], $definition->allowedFormats());
        $this->assertSame(['csv'], ExportDefinition::make('u')->formats(['pdf'])->allowedFormats());
    }
}
