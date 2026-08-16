<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

// >>> MYRA v2.7 [B] START
class RoleDashboardRequest extends FormRequest
{
    /** Authority is the Gate call in the controller, not this request. */
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'dashboard_key' => ['required', 'string', 'max:120'],
            'payload' => ['required', 'array'],
        ];
    }
}
// <<< MYRA v2.7 [B] END
