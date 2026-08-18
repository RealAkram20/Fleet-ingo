<form method="POST" action="{{ route('profile.update') }}">
    @csrf
    @method('patch')

    <div class="grid grid-cols-[repeat(auto-fit,minmax(220px,1fr))] gap-4">
        <x-field label="Name" name="name" required :value="auth()->user()->name" autocomplete="name" />
        <x-field label="Email" name="email" type="email" required :value="auth()->user()->email" autocomplete="username" />
    </div>

    @if (auth()->user() instanceof \Illuminate\Contracts\Auth\MustVerifyEmail && ! auth()->user()->hasVerifiedEmail())
        <div class="mt-4 rounded-sm border border-status-warn/40 bg-status-warn-dim px-4 py-3 text-[13px] text-status-warn">
            <p class="m-0">This email address is not verified.</p>
            <button form="send-verification"
                    class="mt-1 cursor-pointer border-none bg-transparent p-0 text-[13px] text-status-warn underline underline-offset-4">
                Resend the verification message
            </button>
        </div>
    @endif

    <div class="mt-4">
        <x-btn variant="primary">Save Details</x-btn>
    </div>
</form>

@if (auth()->user() instanceof \Illuminate\Contracts\Auth\MustVerifyEmail)
    <form id="send-verification" method="POST" action="{{ route('verification.send') }}">@csrf</form>
@endif
