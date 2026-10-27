<?php

namespace App\Http\Controllers;

use App\Services\StarterTemplates;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class StarterTemplateController extends Controller
{
    public function __construct(private readonly StarterTemplates $starters) {}

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'templates' => ['required', 'array'],
            'templates.*' => [Rule::in(array_keys(StarterTemplates::definitions()))],
        ], ['templates.required' => 'Bitte mindestens eine Vorlage auswählen.']);

        $this->starters->install($request->user(), $data['templates']);

        return redirect()->route('templates.index')->with('status', 'Start-Vorlagen übernommen. Passe sie gern an.');
    }

    public function destroy(Request $request): RedirectResponse
    {
        $this->starters->dismiss($request->user());

        return back()->with('status', 'Alles klar – du startest mit leeren Vorlagen.');
    }
}
