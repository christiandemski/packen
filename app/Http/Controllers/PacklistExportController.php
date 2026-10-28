<?php

namespace App\Http\Controllers;

use App\Models\Trip;
use App\Models\User;
use App\Policies\PacklistEntryPolicy;
use App\Services\PacklistExport;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

/** Packliste drucken (bzw. als PDF sichern) oder als Text für Notiz-Apps holen. */
class PacklistExportController extends Controller
{
    public function __construct(private readonly PacklistExport $export) {}

    public function print(Request $request, Trip $trip): View
    {
        $owner = $this->listOwner($request, $trip);
        $byContainer = $request->boolean('behaeltnisse');

        return view('trips.print', [
            'trip' => $trip,
            'title' => PacklistExport::title($trip, $owner, $owner->isNot($request->user())),
            'subtitle' => PacklistExport::subtitle($trip),
            'sections' => $this->export->sections($trip, $owner, $byContainer, $request->boolean('offen')),
            'byContainer' => $byContainer,
        ]);
    }

    /** text/plain; mit ?download=1 als Datei, sonst zum Teilen/Kopieren. */
    public function text(Request $request, Trip $trip): Response
    {
        $owner = $this->listOwner($request, $trip);
        $format = in_array($request->query('format'), PacklistExport::FORMATS, true) ? $request->query('format') : 'txt';

        $text = $this->export->text($trip, $owner, $format, $request->boolean('behaeltnisse'), $request->boolean('offen'), $owner->isNot($request->user()));

        $response = response($text, 200, [
            'Content-Type' => ($format === 'md' ? 'text/markdown' : 'text/plain').'; charset=UTF-8',
            'Cache-Control' => 'no-store',
        ]);

        if ($request->boolean('download')) {
            $response->headers->set('Content-Disposition', $response->headers->makeDisposition('attachment', $this->export->filename($trip, $format), 'Packliste.'.$format));
        }

        return $response;
    }

    /** Eigene Liste oder – mit ?liste= – eine, die jemand für mich freigegeben hat. */
    private function listOwner(Request $request, Trip $trip): User
    {
        Gate::authorize('view', $trip);
        $user = $request->user();

        if (! $request->filled('liste') || (int) $request->query('liste') === $user->id) {
            return $user;
        }

        $owner = $trip->members()->whereKey((int) $request->query('liste'))->first();
        abort_unless($owner && PacklistEntryPolicy::hasGrant($trip->id, $owner->id, $user->id), 403);

        return $owner;
    }
}
