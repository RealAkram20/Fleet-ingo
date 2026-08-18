<?php

namespace App\Http\Controllers;

use App\Http\Requests\ReadingRequest;
use App\Models\Bike;
use App\Models\Reading;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ReadingController extends Controller
{
    public function index(Request $request): View
    {
        $bikes = Bike::with('rider')->withFleetStats()->orderBy('reg')->get();

        // The chosen bike survives in the URL, so a page refresh or a validation
        // failure never drops the operator back to the first bike in the list.
        $selected = $bikes->firstWhere('id', (int) $request->query('bike'))
            ?? $bikes->first();

        $history = $selected
            ? $selected->readings()->orderByDesc('recorded_on')->limit(10)->get()
            : collect();

        return view('readings.index', [
            'bikes' => $bikes,
            'selected' => $selected,
            'history' => $history,
            'editing' => $request->query('edit') ? Reading::find($request->query('edit')) : null,
        ]);
    }

    public function store(ReadingRequest $request): RedirectResponse
    {
        $reading = Reading::create($request->validated() + [
            'recorded_by' => $request->user()->id,
        ]);

        return redirect()->route('readings.index', ['bike' => $reading->bike_id])
            ->with('status', 'Reading saved — '.number_format($reading->mileage).' km on '.$reading->recorded_on->format('d M Y').'.');
    }

    public function update(ReadingRequest $request, Reading $reading): RedirectResponse
    {
        $reading->update($request->validated());

        return redirect()->route('readings.index', ['bike' => $reading->bike_id])
            ->with('status', 'Reading corrected to '.number_format($reading->mileage).' km.');
    }

    public function destroy(Reading $reading): RedirectResponse
    {
        $bikeId = $reading->bike_id;
        $reading->delete();

        return redirect()->route('readings.index', ['bike' => $bikeId])
            ->with('status', 'Reading deleted.');
    }
}
