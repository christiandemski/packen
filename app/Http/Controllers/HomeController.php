<?php

namespace App\Http\Controllers;

use App\Services\PacklistService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class HomeController extends Controller
{
    public function __invoke(Request $request, PacklistService $packlists): View
    {
        $trips = $request->user()->trips()->with(['members', 'customType'])->orderBy('start_date')->get();

        // Erst abgehakte Reisen wandern nach „Zurückliegend“; vorbei, aber offen, bleibt oben stehen.
        [$past, $upcoming] = $trips->partition(fn ($trip) => $trip->isCompleted());

        return view('home', [
            'upcoming' => $upcoming->values(),
            'past' => $past->sortByDesc('start_date')->values(),
            'progress' => $packlists->progressForTrips($trips->modelKeys(), $request->user()),
        ]);
    }
}
