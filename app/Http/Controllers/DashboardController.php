<?php

namespace App\Http\Controllers;

use App\Models\Bike;
use App\Models\Rider;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function index(Request $request): View
    {
        $filter = $request->query('status');

        $bikes = Bike::query()
            ->with('rider')
            ->withFleetStats()
            ->orderBy('reg')
            ->get();

        $riders = Rider::all();

        $summary = [
            'total' => $bikes->count(),
            'overdue' => $bikes->filter(fn (Bike $b) => $b->serviceStatus()['level'] === 'bad')->count(),
            'due_soon' => $bikes->filter(fn (Bike $b) => $b->serviceStatus()['level'] === 'warn')->count(),
            'licence_flags' => $riders->filter(fn (Rider $r) => $r->licenseStatus()['level'] !== 'ok')->count(),
            'missed_readings' => $bikes->filter(fn (Bike $b) => $this->missedThisWeek($b))->count(),
        ];

        // Filtering happens after the summary so the counts always describe the
        // whole fleet, not whatever subset is on screen.
        if (in_array($filter, ['bad', 'warn'], true)) {
            $bikes = $bikes->filter(fn (Bike $b) => $b->serviceStatus()['level'] === $filter);
        }

        return view('dashboard', [
            'bikes' => $bikes->values(),
            'summary' => $summary,
            'filter' => $filter,
        ]);
    }

    /** A bike with no reading since the start of this week needs chasing. */
    private function missedThisWeek(Bike $bike): bool
    {
        return ! $bike->last_reading_on
            || $bike->last_reading_on < now()->startOfWeek()->toDateString();
    }
}
