<?php

namespace App\Http\Requests;

use App\Models\User;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class UserRequest extends FormRequest
{
    public function rules(): array
    {
        $user = $this->route('user');

        return [
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:190', Rule::unique('users', 'email')->ignore($user?->id)],
            'role' => ['required', Rule::in([User::ROLE_ADMIN, User::ROLE_CLERK])],

            // Required when creating; on edit a blank field means "leave it alone".
            'password' => [$user ? 'nullable' : 'required', 'confirmed', Password::min(8)],
        ];
    }

    /**
     * Nobody may remove the last way into the application. Demoting yourself is
     * blocked outright, because it takes effect immediately and the screen you
     * are on disappears under you.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $user = $this->route('user');

            if (! $user || $this->input('role') === User::ROLE_ADMIN) {
                return;
            }

            if ($user->is($this->user())) {
                $validator->errors()->add('role', 'You cannot remove your own admin rights. Ask another admin to do it.');

                return;
            }

            if (User::where('role', User::ROLE_ADMIN)->whereKeyNot($user->id)->doesntExist()) {
                $validator->errors()->add('role', 'This is the only admin account. Promote someone else first.');
            }
        });
    }

    public function messages(): array
    {
        return [
            'email.unique' => 'That email address already has an account.',
            'password.required' => 'Set a password for the new account.',
            'password.confirmed' => 'The two passwords do not match.',
        ];
    }
}
