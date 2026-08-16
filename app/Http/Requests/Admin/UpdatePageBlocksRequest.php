<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Envelope-level validation only.
 *
 * Field-level coercion, sanitisation and the per-field error keys
 * (blocks.{i}.data.{field}) are SectionWriter's job — an unknown section type is
 * quarantined there rather than rejected here, so this request must not police
 * `type` against the registry.
 */
class UpdatePageBlocksRequest extends FormRequest
{
    /** Mirrors the normaliser's read-side cap. Kept local: no cross-bundle import. */
    public const MAX_BLOCKS = 100;

    public function authorize(): bool
    {
        return $this->user()?->can('settings.edit') === true;
    }

    /** @return array<string,mixed> */
    public function rules(): array
    {
        return [
            'blocks' => ['present', 'array', 'max:' . self::MAX_BLOCKS],
            'blocks.*' => ['array'],
            'blocks.*.id' => ['nullable', 'string', 'max:64'],
            'blocks.*.type' => ['required', 'string', 'max:64'],
            'blocks.*.enabled' => ['nullable', 'boolean'],
            'blocks.*.variant' => ['nullable', 'array'],
            'blocks.*.data' => ['nullable', 'array'],
        ];
    }

    /** @return array<string,string> */
    public function messages(): array
    {
        return [
            'blocks.max' => __('A page may hold at most :max sections.', ['max' => self::MAX_BLOCKS]),
        ];
    }
}
