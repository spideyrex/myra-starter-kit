<?php

namespace App\Admin\Import;

use Illuminate\Contracts\Auth\Authenticatable;

/**
 * The Import/Export demo has no table behind it, so this definition is
 * dryRunOnly: the full pipeline (staging, mapping, per-cell validation,
 * resumable chunks, failures CSV) runs, but nothing is written.
 */
final class DemoContactsImport
{
    public static function definition(): ImportDefinition
    {
        return ImportDefinition::make('demo-contacts')
            ->permission('demo.view')
            ->dryRunOnly()
            ->chunkSize(10)
            ->maxRows(5000)
            ->columns([
                ImportColumn::make('name')
                    ->label('Name')
                    ->requiredMapping()
                    ->guess(['full name', 'contact'])
                    ->rules(['required', 'string', 'max:255'])
                    ->example('Ada Lovelace'),

                ImportColumn::make('email')
                    ->label('Email')
                    ->requiredMapping()
                    ->guess(['e-mail', 'email address'])
                    ->rules(['required', 'email', 'max:255'])
                    ->example('ada@example.com'),

                ImportColumn::make('phone')
                    ->label('Phone')
                    ->guess(['mobile', 'tel'])
                    ->rules(['nullable', 'string', 'max:32'])
                    ->example('+60 12-345 6789'),

                ImportColumn::make('company')
                    ->label('Company')
                    ->guess(['organisation', 'organization', 'employer'])
                    ->rules(['nullable', 'string', 'max:255'])
                    ->example('Analytical Engines Ltd'),

                ImportColumn::make('status')
                    ->label('Status')
                    ->default('pending')
                    ->rules(['nullable', 'in:active,suspended,pending'])
                    ->example('active'),
            ])
            ->authorizeRow(fn (array $row, ?Authenticatable $actor) => $actor !== null);
    }
}
