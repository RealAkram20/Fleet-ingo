<?php

namespace App\Http\Controllers;

use App\Http\Requests\BikeRequest;
use App\Models\Bike;
use App\Models\Rider;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class BikeController extends Controller
{
    public function index(Request $request): View
    {
        $search = trim((string) $request->query('q'));

        $bikes = Bike::query()
            ->with('rider')
            ->withFleetStats()
            ->when($search, fn ($q) => $q->where(function ($q) use ($search) {
                $q->where('reg', 'like', "%{$search}%")
                    ->orWhere('model', 'like', "%{$search}%")
                    ->orWhereHas('rider', fn ($r) => $r->where('name', 'like', "%{$search}%"));
            }))
            ->orderBy('reg')
            ->get();

        return view('bikes.index', [
            'bikes' => $bikes,
            'riders' => Rider::orderBy('name')->get(),
            'search' => $search,
            'editing' => $request->query('edit') ? Bike::find($request->query('edit')) : null,
        ]);
    }

    public function store(BikeRequest $request): RedirectResponse
    {
        $bike = Bike::create($request->validated());

        return redirect()->route('bikes.index')
            ->with('status', "Bike {$bike->reg} saved. Log a reading to set its mileage.");
    }

    public function edit(Bike $bike): RedirectResponse
    {
        return redirect()->route('bikes.index', ['edit' => $bike->id]);
    }

    public function update(BikeRequest $request, Bike $bike): RedirectResponse
    {
        $bike->update($request->validated());

        return redirect()->route('bikes.index')
            ->with('status', "Bike {$bike->reg} updated.");
    }

    public function destroy(Bike $bike): RedirectResponse
    {
        $reg = $bike->reg;

        // Soft delete keeps the readings and service history queryable.
        $bike->delete();

        return redirect()->route('bikes.index')
            ->with('status', "Bike {$reg} removed from the fleet.");
    }
}
