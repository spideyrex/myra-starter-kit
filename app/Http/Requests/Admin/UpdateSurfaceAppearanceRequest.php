<?php

namespace App\Http\Requests\Admin;

use App\Appearance\Admin\AppearanceWriter;
use App\Appearance\Admin\AuthPreviewSlot;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * Everything is an allowlisted choice. `auth_bg_image_path` is deliberately
 * absent: a path only ever enters through the upload endpoint, so a hand-typed
 * one can never reach the unauthenticated login page.
 */
class UpdateSurfaceAppearanceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return self::ruleSet();
    }

    /** Shared with the preview endpoint so the two can never diverge. */
    public static function ruleSet(): array
    {
        return [
            'auth_layout' => ['required', 'string', Rule::in(AuthPreviewSlot::layoutIds())],
            'auth_flip' => ['required', 'boolean'],
            'auth_show_tagline' => ['required', 'boolean'],
            'auth_bg_type' => ['required', 'string', Rule::in(AppearanceWriter::TYPES)],
            'auth_bg_color' => ['nullable', 'string', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'auth_bg_recipe' => ['nullable', 'string', Rule::in([...AppearanceWriter::GRADIENTS, ...AppearanceWriter::PATTERNS])],
            'auth_bg_scrim' => ['required', 'string', Rule::in(AppearanceWriter::SCRIMS)],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $v) {
            $type = $this->input('auth_bg_type');
            $recipe = $this->input('auth_bg_recipe');

            if (! is_string($recipe) || $recipe === '') {
                return;
            }

            if ($type === 'gradient' && ! in_array($recipe, AppearanceWriter::GRADIENTS, true)) {
                $v->errors()->add('auth_bg_recipe', __('The selected background style does not belong to the chosen background type.'));
            }

            if ($type === 'pattern' && ! in_array($recipe, AppearanceWriter::PATTERNS, true)) {
                $v->errors()->add('auth_bg_recipe', __('The selected background style does not belong to the chosen background type.'));
            }
        });
    }

    /** Only the rows this form owns, coerced to their stored shape. */
    public function toRows(): array
    {
        $data = $this->validated();
        $recipe = $data['auth_bg_recipe'] ?? null;
        $type = (string) $data['auth_bg_type'];

        return [
            'auth_layout' => (string) $data['auth_layout'],
            'auth_flip' => (bool) $data['auth_flip'],
            'auth_show_tagline' => (bool) $data['auth_show_tagline'],
            'auth_bg_type' => $type,
            'auth_bg_color' => isset($data['auth_bg_color']) && $data['auth_bg_color'] !== null
                ? strtolower((string) $data['auth_bg_color'])
                : null,
            'auth_bg_recipe' => in_array($type, ['gradient', 'pattern'], true) && is_string($recipe) && $recipe !== ''
                ? $recipe
                : null,
            'auth_bg_scrim' => (string) $data['auth_bg_scrim'],
        ];
    }
}
