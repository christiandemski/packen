<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\View\View;

class HomeController extends Controller
{
    public function __invoke(Request $request): View
    {
        $trips = $request->user()->trips()->with('members')->orderBy('start_date')->get();

        [$past, $upcoming] = $trips->partition(fn ($trip) => $trip->status() === 'past');

        return view('home', [
            'upcoming' => $upcoming->values(),
            'past' => $past->sortByDesc('start_date')->values(),
        ]);
    }
}
