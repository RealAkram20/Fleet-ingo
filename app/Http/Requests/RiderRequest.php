<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class RiderRequest extends FormRequest
{
    public function rules(): array
    {
        $riderId = $this->route('rider')?->id;

        return [
            'name' => ['required', 'string', 'max:120', Rule::unique('riders', 'name')->ignore($riderId)->whereNull('deleted_at')],
            'phone' => ['nullable', 'string', 'max:40'],
            'license_number' => ['nullable', 'string', 'max:60'],
            'license_expiry' => ['nullable', 'date'],
            'is_active' => ['boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'Enter the rider name.',
            'name.unique' => 'A rider with this name is already on file.',
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['is_active' => $this->boolean('is_active')]);
    }
}
