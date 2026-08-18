<?php

namespace App\Http\Controllers;

use App\Http\Requests\UserRequest;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\View\View;

class UserController extends Controller
{
    public function index(Request $request): View
    {
        $search = trim((string) $request->query('q'));

        $users = User::query()
            ->when($search, fn ($q) => $q->where(fn ($q) => $q
                ->where('name', 'like', "%{$search}%")
                ->orWhere('email', 'like', "%{$search}%")))
            ->orderByRaw("CASE WHEN role = ? THEN 0 ELSE 1 END", [User::ROLE_ADMIN])
            ->orderBy('name')
            ->get();

        return view('users.index', [
            'users' => $users,
            'search' => $search,
            'editing' => $request->query('edit') ? User::find($request->query('edit')) : null,
            'adminCount' => User::where('role', User::ROLE_ADMIN)->count(),
        ]);
    }

    public function store(UserRequest $request): RedirectResponse
    {
        $data = $request->validated();

        $user = User::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'role' => $data['role'],
            'password' => Hash::make($data['password']),
        ]);

        return redirect()->route('users.index')
            ->with('status', "Account created for {$user->name} as {$user->role}.");
    }

    public function edit(User $user): RedirectResponse
    {
        return redirect()->route('users.index', ['edit' => $user->id]);
    }

    public function update(UserRequest $request, User $user): RedirectResponse
    {
        $data = $request->validated();

        $user->fill([
            'name' => $data['name'],
            'email' => $data['email'],
            'role' => $data['role'],
        ]);

        // A blank password box means "leave the password as it is".
        if (! empty($data['password'])) {
            $user->password = Hash::make($data['password']);
        }

        $user->save();

        return redirect()->route('users.index')
            ->with('status', "{$user->name} updated.");
    }

    public function destroy(Request $request, User $user): RedirectResponse
    {
        if ($user->is($request->user())) {
            return redirect()->route('users.index')
                ->withErrors(['user' => 'You cannot delete the account you are signed in with.']);
        }

        if ($user->isAdmin() && User::where('role', User::ROLE_ADMIN)->count() <= 1) {
            return redirect()->route('users.index')
                ->withErrors(['user' => 'This is the only admin account, so it cannot be deleted.']);
        }

        $name = $user->name;
        $user->delete();

        return redirect()->route('users.index')
            ->with('status', "{$name} no longer has access.");
    }
}
