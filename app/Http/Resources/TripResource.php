<?php

namespace App\Http\Resources;

use App\Enums\Transport;
use App\Models\Trip;
use App\Models\TripStage;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Trip */
class TripResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'external_id' => $this->external_id,
            'name' => $this->name,
            'start_date' => $this->start_date->toDateString(),
            'end_date' => $this->end_date->toDateString(),
            'days' => $this->days(),
            'nights' => $this->nights(),
            'type' => $this->type->value,
            'mode' => $this->mode->value,
            'accommodation' => $this->accommodation?->value,
            'transports' => ($this->transports ?? collect())->map(fn (Transport $t) => $t->value)->values(),
            'location' => $this->location,
            'stages' => $this->stages->map(fn (TripStage $s) => ['date' => $s->date->toDateString(), 'location' => $s->location])->values(),
            'url' => route('trips.show', $this->resource),
        ];
    }
}
