<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;

class ServiceRecordRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'serviced_on' => ['required', 'date', 'before_or_equal:today'],
            'mileage' => ['required', 'integer', 'min:0', 'max:2000000'],
            'cost' => ['nullable', 'numeric', 'min:0', 'max:1000000'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $bike = $this->route('bike');
            $previous = $bike->serviceRecords()->max('mileage');

            if ($previous !== null && (int) $this->input('mileage') < (int) $previous) {
                $validator->errors()->add('mileage', sprintf(
                    'Service mileage (%s km) is below the previous service at %s km.',
                    number_format((int) $this->input('mileage')),
                    number_format((int) $previous),
                ));
            }
        });
    }

    public function messages(): array
    {
        return [
            'serviced_on.before_or_equal' => 'A service cannot be dated in the future.',
            'mileage.required' => 'Enter the odometer reading at the time of service.',
        ];
    }
}
