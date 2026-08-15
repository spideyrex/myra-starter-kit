<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class TableViewRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /** A saved report view is an ordinary view with a `report:`-prefixed key. */
    public function isReportView(): bool
    {
        return str_starts_with((string) $this->input('table_key'), 'report:');
    }

    public function rules(): array
    {
        $rules = [
            'table_key' => ['required', 'string', 'max:120', 'regex:/^[a-z0-9][a-z0-9._:-]*$/i'],
            'name' => [
                'required', 'string', 'max:60',
                Rule::notIn([\App\Models\TableView::COLUMNS_NAME]),
                Rule::unique('table_views')
                    ->where(fn ($q) => $q
                        ->where('user_id', $this->user()->id)
                        ->where('table_key', (string) $this->input('table_key')))
                    ->ignore($this->route('tableView')),
            ],
            'visibility' => ['required', Rule::in(['private', 'team'])],
            'is_default' => ['boolean'],
        ];

        // A report view carries a ReportState, not a DataTable payload. Its
        // shape, size and cross-filter count are asserted by ReportShape; the
        // state itself is re-validated by ReportRequest::parse on replay.
        if ($this->isReportView()) {
            $rules['payload'] = ['required', 'array'];

            return $rules;
        }

        return $rules + [
            // `array:...` pins the top-level keys: an unlisted key is a
            // validation failure, not a silent extra.
            'payload' => ['required', 'array:search,sort,direction,per_page,filters,dateRanges,query,columns,columnOrder'],
            'payload.search' => ['nullable', 'string', 'max:200'],
            'payload.sort' => ['nullable', 'string', 'max:64', 'regex:/^[a-z_][a-z0-9_]*$/i'],
            'payload.direction' => ['nullable', Rule::in(['asc', 'desc'])],
            'payload.per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
            'payload.filters' => ['array', 'max:32'],
            'payload.filters.*' => ['nullable', 'string', 'max:200'],
            'payload.dateRanges' => ['array', 'max:8'],
            'payload.dateRanges.*.from' => ['nullable', 'date_format:Y-m-d'],
            'payload.dateRanges.*.to' => ['nullable', 'date_format:Y-m-d'],
            'payload.query' => ['nullable', 'array', 'max:8'],
            'payload.columns' => ['array', 'max:64'],
            'payload.columns.*' => ['boolean'],
            'payload.columnOrder' => ['array', 'max:64'],
            'payload.columnOrder.*' => ['string', 'max:64', 'regex:/^[a-z_][a-z0-9_.]*$/i'],
        ];
    }
}
