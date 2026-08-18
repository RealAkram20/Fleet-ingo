<?php

namespace App\Http\Requests;

use App\Models\Reading;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ReadingRequest extends FormRequest
{
    public function rules(): array
    {
        $reading = $this->route('reading');

        return [
            'bike_id' => ['required', 'exists:bikes,id'],
            // One-reading-per-bike-per-day is enforced in withValidator() with
            // whereDate(). Rule::unique() compares the raw column, and Laravel
            // writes a date cast as "Y-m-d 00:00:00" — MySQL truncates that into
            // a DATE column but SQLite stores it verbatim, so the rule would
            // pass in tests and only fail at the database constraint.
            'recorded_on' => ['required', 'date', 'before_or_equal:today'],
            'mileage' => ['required', 'integer', 'min:0', 'max:2000000'],
            'note' => ['nullable', 'string', 'max:255'],
        ];
    }

    /**
     * An odometer only rises. Compare against the readings either side of this
     * date rather than a stored "current mileage" field, so a back-dated entry
     * is checked against its actual neighbours instead of the newest figure.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $editing = $this->route('reading');

            $duplicate = Reading::where('bike_id', $this->input('bike_id'))
                ->whereDate('recorded_on', $this->date('recorded_on'))
                ->when($editing, fn ($q) => $q->whereKeyNot($editing->id))
                ->exists();

            if ($duplicate) {
                $validator->errors()->add(
                    'recorded_on',
                    'This bike already has a reading on that date. Edit that one instead.',
                );

                return;
            }

            $before = Reading::where('bike_id', $this->input('bike_id'))
                ->where('recorded_on', '<', $this->date('recorded_on'))
                ->when($editing, fn ($q) => $q->whereKeyNot($editing->id))
                ->orderByDesc('recorded_on')
                ->first();

            $after = Reading::where('bike_id', $this->input('bike_id'))
                ->where('recorded_on', '>', $this->date('recorded_on'))
                ->when($editing, fn ($q) => $q->whereKeyNot($editing->id))
                ->orderBy('recorded_on')
                ->first();

            $mileage = (int) $this->input('mileage');

            if ($before && $mileage < $before->mileage) {
                $validator->errors()->add('mileage', sprintf(
                    'This reading (%s km) is below the %s km recorded on %s. Check the number.',
                    number_format($mileage),
                    number_format($before->mileage),
                    $before->recorded_on->format('d M Y'),
                ));
            }

            if ($after && $mileage > $after->mileage) {
                $validator->errors()->add('mileage', sprintf(
                    'This reading (%s km) is above the %s km already recorded on %s.',
                    number_format($mileage),
                    number_format($after->mileage),
                    $after->recorded_on->format('d M Y'),
                ));
            }
        });
    }

    public function messages(): array
    {
        return [
            'bike_id.required' => 'Choose a bike.',
            'recorded_on.before_or_equal' => 'A reading cannot be dated in the future.',
            'mileage.required' => 'Enter the odometer reading.',
        ];
    }
}
