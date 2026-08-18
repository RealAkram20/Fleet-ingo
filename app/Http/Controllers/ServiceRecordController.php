<?php

namespace App\Http\Controllers;

use App\Http\Requests\ServiceRecordRequest;
use App\Models\Bike;
use Illuminate\Http\RedirectResponse;

class ServiceRecordController extends Controller
{
    /**
     * Logging a service appends a row rather than overwriting the bike, so the
     * history — and the cost of running each machine — survives.
     */
    public function store(ServiceRecordRequest $request, Bike $bike): RedirectResponse
    {
        $bike->serviceRecords()->create($request->validated() + [
            'recorded_by' => $request->user()->id,
        ]);

        return back()->with('status', "Service logged for {$bike->reg}.");
    }
}
