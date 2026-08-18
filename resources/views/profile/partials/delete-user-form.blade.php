<p class="mb-4 mt-0 max-w-prose text-[13px] text-plate-300">
    Closing your account removes your access for good. Readings and services you logged stay on the
    record, listed against no one. Enter your password to confirm.
</p>

<form method="POST" action="{{ route('profile.destroy') }}"
      onsubmit="return confirm('Close your account? You will be signed out immediately and cannot sign back in.')">
    @csrf
    @method('delete')

    <div class="max-w-xs">
        <x-field label="Your password" name="password" type="password" required autocomplete="current-password" />
    </div>

    <div class="mt-4">
        <x-btn variant="danger">Close My Account</x-btn>
    </div>
</form>
