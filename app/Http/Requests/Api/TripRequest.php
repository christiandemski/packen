<?php

namespace App\Http\Requests\Api;

use App\Enums\Accommodation;
use App\Enums\Transport;
use App\Enums\TripType;
use App\Models\Trip;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * Reise anlegen (POST) oder ändern (PATCH, nur mitgeschickte Felder).
 * Etappen ersetzen beim Mitschicken immer alle bisherigen.
 */
class TripRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Rechte prüft der Controller über die TripPolicy.
    }

    public function rules(): array
    {
        $required = $this->isMethod('POST') ? 'required' : 'sometimes';

        return [
            'external_id' => ['sometimes', 'nullable', 'string', 'max:64'],
            'name' => [$required, 'string', 'max:120'],
            'start_date' => [$required, 'date_format:Y-m-d'],
            'end_date' => [$required, 'date_format:Y-m-d'],
            'type' => ['sometimes', Rule::enum(TripType::class)],
            'accommodation' => ['sometimes', 'nullable', Rule::enum(Accommodation::class)],
            'transports' => ['sometimes', 'array'],
            'transports.*' => [Rule::enum(Transport::class)],
            'location' => ['sometimes', 'nullable', 'string', 'max:120'],
            'stages' => ['sometimes', 'nullable', 'array', 'max:366'],
            'stages.*.date' => ['required', 'date_format:Y-m-d', 'distinct'],
            'stages.*.location' => ['required', 'string', 'max:120'],
        ];
    }

    public function after(): array
    {
        return [function (Validator $validator) {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            /** @var Trip|null $trip */
            $trip = $this->route('trip');
            $start = Carbon::parse($this->input('start_date', $trip?->start_date));
            $end = Carbon::parse($this->input('end_date', $trip?->end_date));

            if ($end->lt($start)) {
                $validator->errors()->add('end_date', 'Das Ende liegt vor dem Beginn.');

                return;
            }
            if ($start->diffInDays($end) > 365) {
                $validator->errors()->add('end_date', 'Eine Reise darf höchstens ein Jahr dauern.');

                return;
            }

            foreach ($this->input('stages') ?? [] as $i => $stage) {
                if (! Carbon::parse($stage['date'])->between($start, $end)) {
                    $validator->errors()->add("stages.{$i}.date", 'Die Etappe liegt außerhalb der Reise.');
                }
            }
        }];
    }

    /** @return array<string, mixed> */
    public function tripData(): array
    {
        $data = $this->validated();

        if (array_key_exists('stages', $data)) {
            $data['stages'] = collect($data['stages'] ?? [])->sortBy('date')->values()->all();
        }
        if (array_key_exists('transports', $data)) {
            $data['transports'] = array_values(array_unique($data['transports']));
        }

        return $data;
    }
}
