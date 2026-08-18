<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;
use Illuminate\Support\Facades\Validator;

/**
 * Public registration is closed, so staff accounts are created here.
 */
class CreateUser extends Command
{
    protected $signature = 'ingo:user
                            {email? : The sign-in address}
                            {--name= : Display name}
                            {--role=clerk : admin or clerk}
                            {--password= : Skips the prompt; use only in scripts}';

    protected $description = 'Create or update a fleet log account';

    public function handle(): int
    {
        $email = $this->argument('email') ?: $this->ask('Email address');
        $name = $this->option('name') ?: $this->ask('Full name');
        $role = $this->option('role');
        $password = $this->option('password') ?: $this->secret('Password');

        $validator = Validator::make(
            compact('email', 'name', 'role', 'password'),
            [
                'email' => ['required', 'email'],
                'name' => ['required', 'string', 'max:120'],
                'role' => ['required', 'in:'.User::ROLE_ADMIN.','.User::ROLE_CLERK],
                'password' => ['required', Password::min(8)],
            ],
        );

        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $error) {
                $this->error($error);
            }

            return self::FAILURE;
        }

        $existing = User::where('email', $email)->exists();

        $user = User::updateOrCreate(
            ['email' => $email],
            ['name' => $name, 'role' => $role, 'password' => Hash::make($password)],
        );

        $this->info(sprintf(
            '%s %s (%s) as %s.',
            $existing ? 'Updated' : 'Created',
            $user->name,
            $user->email,
            $user->role,
        ));

        return self::SUCCESS;
    }
}
