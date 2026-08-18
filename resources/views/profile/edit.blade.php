@extends('layouts.app')
@section('title', 'My Account')

@section('content')

<h2 class="m-0 font-display text-xl uppercase tracking-wide">My Account</h2>
<p class="mb-5 mt-1 text-[13px] text-plate-300">Your own name, sign-in address and password.</p>

<x-panel title="Details" subtitle="Changing your email address means signing in with the new one.">
    @include('profile.partials.update-profile-information-form')
</x-panel>

<x-panel title="Password" subtitle="Use at least 8 characters.">
    @include('profile.partials.update-password-form')
</x-panel>

<x-panel title="Close my account"
         subtitle="Everything you logged is kept; only your access is removed.">
    @include('profile.partials.delete-user-form')
</x-panel>

@endsection
