<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class BikeRequest extends FormRequest
{
    public function rules(): array
    {
        $bikeId = $this->route('bike')?->id;

        return [
            // Unique registration closes the duplicate-bike gap in the old app.
            'reg' => ['required', 'string', 'max:30', Rule::unique('bikes', 'reg')->ignore($bikeId)->whereNull('deleted_at')],
            'model' => ['nullable', 'string', 'max:120'],
            'rider_id' => ['nullable', 'exists:riders,id'],

            // Required rather than defaulted: the old form turned a blank box
            // into 0 without saying so.
            'service_interval_km' => ['required', 'integer', 'min:100', 'max:100000'],
            'service_interval_months' => ['nullable', 'integer', 'min:1', 'max:60'],
            'is_active' => ['boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'reg.required' => 'Enter the registration number.',
            'reg.unique' => 'That registration number is already on another bike.',
            'service_interval_km.required' => 'Enter the service interval in kilometres.',
            'service_interval_km.min' => 'A service interval below 100 km is almost certainly a typo.',
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'reg' => strtoupper(trim((string) $this->input('reg'))),
            'rider_id' => $this->input('rider_id') ?: null,
            'service_interval_months' => $this->input('service_interval_months') ?: null,
            'is_active' => $this->boolean('is_active'),
        ]);
    }
}
