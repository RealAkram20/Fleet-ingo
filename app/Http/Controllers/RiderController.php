<?php

namespace App\Http\Controllers;

use App\Http\Requests\RiderRequest;
use App\Models\Rider;
use App\Support\Search;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class RiderController extends Controller
{
    public function index(Request $request): View
    {
        $search = trim((string) $request->query('q'));

        $riders = Rider::query()
            ->withCount('bikes')
            ->when($search, fn ($q) => $q->where(function ($q) use ($search) {
                $q->whereRaw(Search::clause('name'), [Search::like($search)])
                    ->orWhereRaw(Search::clause('phone'), [Search::like($search)])
                    ->orWhereRaw(Search::clause('license_number'), [Search::like($search)]);
            }))
            ->orderBy('name')
            ->get();

        return view('riders.index', [
            'riders' => $riders,
            'search' => $search,
            // is_scalar: ?edit[]=1 would hand find() an array and 500 the page.
            'editing' => is_scalar($id = $request->query('edit')) ? Rider::find($id) : null,
        ]);
    }

    public function store(RiderRequest $request): RedirectResponse
    {
        $rider = Rider::create($request->validated());

        return redirect()->route('riders.index')
            ->with('status', "Rider {$rider->name} saved.");
    }

    public function edit(Rider $rider): RedirectResponse
    {
        // The form lives on the index page, so editing is a query string on it.
        return redirect()->route('riders.index', ['edit' => $rider->id]);
    }

    public function update(RiderRequest $request, Rider $rider): RedirectResponse
    {
        $rider->update($request->validated());

        return redirect()->route('riders.index')
            ->with('status', "Rider {$rider->name} updated.");
    }

    public function destroy(Rider $rider): RedirectResponse
    {
        $name = $rider->name;

        // Soft delete: the rider leaves the roster but their bikes keep the
        // assignment, so past readings still say who was riding.
        $rider->delete();

        return redirect()->route('riders.index')
            ->with('status', "Rider {$name} removed from the roster.");
    }
}
