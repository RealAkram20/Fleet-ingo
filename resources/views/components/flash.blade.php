@if (session('status'))
    <div class="mb-5 rounded-sm border border-status-ok/40 bg-status-ok-dim px-4 py-3 text-[14px] text-status-ok">
        {{ session('status') }}
    </div>
@endif

@if ($errors->any())
    <div class="mb-5 rounded-sm border border-status-bad/40 bg-status-bad-dim px-4 py-3 text-[14px] text-status-bad">
        <p class="m-0 font-semibold">{{ $errors->count() === 1 ? 'There is a problem with this entry:' : 'There are problems with this entry:' }}</p>
        <ul class="mb-0 mt-2 list-disc space-y-1 pl-5">
            @foreach ($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    </div>
@endif
