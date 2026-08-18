@extends('layouts.app')
@section('title', 'Users')

@section('content')

<h2 class="m-0 font-display text-xl uppercase tracking-wide">Users</h2>
<p class="mb-5 mt-1 text-[13px] text-plate-300">
    Who can sign in, and what they are allowed to do. Admins manage the fleet, accounts and settings;
    clerks log readings and mark bikes serviced.
</p>

<x-panel :title="$editing ? 'Edit Account — '.$editing->name : 'Add Account'"
         :subtitle="$editing ? 'Leave the password blank to keep the current one.' : null">
    <form method="POST" action="{{ $editing ? route('users.update', $editing) : route('users.store') }}">
        @csrf
        @if ($editing) @method('PATCH') @endif

        <div class="grid grid-cols-[repeat(auto-fit,minmax(min(100%,220px),1fr))] gap-4">
            <x-field label="Full name" name="name" required :value="$editing?->name" placeholder="e.g. Yard Clerk" />
            <x-field label="Email" name="email" type="email" required :value="$editing?->email" placeholder="name@ingo.local" />

            <x-select-field label="Role" name="role" required
                            hint="Admins can add users and change settings.">
                <option value="clerk" @selected(old('role', $editing?->role ?? 'clerk') === 'clerk')>Clerk</option>
                <option value="admin" @selected(old('role', $editing?->role) === 'admin')>Admin</option>
            </x-select-field>

            <x-field :label="$editing ? 'New password' : 'Password'" name="password" type="password"
                     :required="! $editing" autocomplete="new-password"
                     :hint="$editing ? 'Blank leaves it unchanged.' : 'At least 8 characters.'" />

            <x-field label="Confirm password" name="password_confirmation" type="password"
                     :required="! $editing" autocomplete="new-password" />
        </div>

        <div class="mt-4 flex flex-wrap gap-2">
            <x-btn variant="primary">{{ $editing ? 'Save Account' : 'Create Account' }}</x-btn>
            @if ($editing)
                <x-btn variant="ghost" :href="route('users.index')">Cancel</x-btn>
            @endif
        </div>
    </form>
</x-panel>

<x-panel flush>
    <div class="flex flex-wrap items-center justify-between gap-3 border-b border-asphalt-600 px-4 py-3.5 sm:px-5">
        <h2 class="m-0 font-display text-[15px] uppercase tracking-wide">
            {{ $users->count() }} {{ Str::plural('account', $users->count()) }}
            <span class="ml-2 font-mono text-[10px] tracking-widest text-plate-300">
                {{ $adminCount }} {{ Str::plural('admin', $adminCount) }}
            </span>
        </h2>
        <form method="GET" action="{{ route('users.index') }}" class="flex w-full gap-2 sm:w-auto">
            <input type="search" name="q" value="{{ $search }}" placeholder="Search name or email"
                   class="min-w-0 flex-1 rounded-sm border border-asphalt-600 bg-asphalt-900 px-3 py-2 text-base text-plate-50 outline-none placeholder:text-plate-300/40 focus:border-ingo-500 sm:w-56 sm:flex-none sm:py-1.5 sm:text-[13px]">
            <x-btn variant="small">Search</x-btn>
            @if ($search)
                <x-btn variant="ghost" :href="route('users.index')">Clear</x-btn>
            @endif
        </form>
    </div>

    @if ($users->isEmpty())
        <p class="m-0 px-5 py-8 text-center text-[14px] text-plate-300">No accounts match that search.</p>
    @else
        <div class="overflow-x-auto">
            <table class="table-cards w-full border-collapse text-[14px]">
                <thead>
                    <tr class="bg-asphalt-900 text-left font-mono text-[10px] uppercase tracking-widest text-plate-300">
                        <th class="px-5 py-2.5 font-semibold">Name</th>
                        <th class="px-5 py-2.5 font-semibold">Email</th>
                        <th class="px-5 py-2.5 font-semibold">Role</th>
                        <th class="px-5 py-2.5 font-semibold">Added</th>
                        <th class="px-5 py-2.5"></th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($users as $user)
                        @php
                            $isSelf = $user->is(auth()->user());
                            $isLastAdmin = $user->isAdmin() && $adminCount <= 1;
                        @endphp
                        <tr class="border-t border-asphalt-600 {{ $editing?->is($user) ? 'bg-asphalt-700' : '' }}">
                            <td data-label="Name" class="px-5 py-3">
                                {{ $user->name }}
                                @if ($isSelf)
                                    <span class="ml-1.5 font-mono text-[10px] uppercase tracking-widest text-ingo-500">you</span>
                                @endif
                            </td>
                            <td data-label="Email" class="break-all px-5 py-3 font-mono text-[13px] text-plate-300">{{ $user->email }}</td>
                            <td data-label="Role" class="px-5 py-3">
                                <x-status-badge :status="$user->isAdmin()
                                    ? ['level' => 'warn', 'label' => 'Admin']
                                    : ['level' => 'ok', 'label' => 'Clerk']" />
                            </td>
                            <td data-label="Added" class="px-5 py-3 tabular-nums text-plate-300">{{ $user->created_at?->format('d M Y') }}</td>
                            <td class="actions px-5 py-3">
                                <div class="flex flex-wrap items-center justify-end gap-2">
                                    <x-btn variant="small" :href="route('users.index', ['edit' => $user->id])">Edit</x-btn>

                                    @if ($isSelf)
                                        <span class="font-mono text-[10px] uppercase tracking-widest text-plate-300/50">signed in</span>
                                    @elseif ($isLastAdmin)
                                        <span class="font-mono text-[10px] uppercase tracking-widest text-plate-300/50">only admin</span>
                                    @else
                                        <form method="POST" action="{{ route('users.destroy', $user) }}"
                                              data-confirm='Remove access for {{ $user->name }}? Readings they logged are kept.'>
                                            @csrf @method('DELETE')
                                            <x-btn variant="danger">Remove</x-btn>
                                        </form>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
</x-panel>

@endsection
