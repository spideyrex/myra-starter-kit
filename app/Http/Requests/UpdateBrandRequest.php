<?php

namespace App\Http\Requests;

use App\Brand\BrandPalette;
use App\Brand\BrandTypography;
use App\Rules\SafeImage;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateBrandRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('brand.update') === true;
    }

    public function rules(): array
    {
        $hex = ['nullable', 'string', 'regex:/^#[0-9a-fA-F]{6}$/'];

        return [
            'enabled' => ['required', 'boolean'],
            'name' => ['required', 'string', 'max:120'],
            'short_name' => ['nullable', 'string', 'max:24'],
            'tagline' => ['nullable', 'string', 'max:200'],
            'description' => ['nullable', 'string', 'max:400'],
            'primary' => ['required', 'string', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'accent' => $hex,
            'sidebar_background' => $hex,
            'sidebar_foreground' => $hex,
            'sidebar_accent' => $hex,
            'preset' => ['nullable', Rule::in(array_keys(BrandPalette::PRESETS))],
            'font_sans' => ['required', Rule::in(BrandTypography::SANS)],
            'font_mono' => ['required', Rule::in(BrandTypography::MONO)],
            'radius' => ['required', Rule::in(BrandTypography::RADII)],
            'dark_default' => ['required', 'boolean'],
            'logo_position' => ['required', Rule::in(['sidebar', 'header'])],
            'logo' => ['nullable', new SafeImage('logo')],
            'logo_dark' => ['nullable', new SafeImage('logo_dark')],
            'mark' => ['nullable', new SafeImage('mark')],
            'og_image' => ['nullable', new SafeImage('og_image')],
            'favicon' => ['nullable', new SafeImage('favicon')],
            'remove_logo' => ['sometimes', 'boolean'],
            'remove_logo_dark' => ['sometimes', 'boolean'],
            'remove_mark' => ['sometimes', 'boolean'],
            'remove_favicon' => ['sometimes', 'boolean'],
            'remove_og_image' => ['sometimes', 'boolean'],
        ];
    }
}
