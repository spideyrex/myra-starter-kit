<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class DashboardLayoutRequest extends FormRequest
{
    /** Authority is the policy in the controller, not this request. */
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
