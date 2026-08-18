<form method="POST" action="{{ route('password.update') }}">
    @csrf
    @method('put')

    <div class="grid grid-cols-[repeat(auto-fit,minmax(220px,1fr))] gap-4">
        <x-field label="Current password" name="current_password" type="password" required
                 autocomplete="current-password" />
        <x-field label="New password" name="password" type="password" required
                 autocomplete="new-password" hint="At least 8 characters." />
        <x-field label="Confirm new password" name="password_confirmation" type="password" required
                 autocomplete="new-password" />
    </div>

    <div class="mt-4">
        <x-btn variant="primary">Change Password</x-btn>
    </div>
</form>
