<?php

namespace App\Http\Requests;

use App\Enums\Accommodation;
use App\Enums\Transport;
use App\Enums\TripMode;
use App\Enums\TripType;
use App\Models\CustomTripType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class TripRequest extends FormRequest
{
    /** Auswahlwert „neue Reiseart anlegen“ */
    public const NEW_TYPE = 'new';

    /** Größter Zoom beim Bildausschnitt in Prozent */
    public const MAX_COVER_ZOOM = 250;

    public function authorize(): bool
    {
        return true; // Rechte prüft der Controller über die TripPolicy.
    }

    /** Nicht angekreuzte Checkboxen werden gar nicht gesendet – „nichts gewählt“ heißt leere Liste. */
    protected function prepareForValidation(): void
    {
        $this->merge(['transports' => array_values(array_unique((array) $this->input('transports', [])))]);
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:120'],
            'location' => ['nullable', 'string', 'max:120'],
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after_or_equal:start_date'],
            'type' => ['required', 'string', Rule::in([
                ...array_column(TripType::cases(), 'value'),
                ...CustomTripType::accessibleBy($this->user())->get()->map->key(),
                self::NEW_TYPE,
            ])],
            'new_type_name' => ['nullable', 'required_if:type,'.self::NEW_TYPE, 'string', 'max:60',
                Rule::unique('custom_trip_types', 'name')->where('owner_id', $this->user()->id)],
            'new_type_icon' => ['nullable', 'required_if:type,'.self::NEW_TYPE, Rule::in(CustomTripType::ICONS)],
            'new_type_color' => ['nullable', 'required_if:type,'.self::NEW_TYPE, Rule::in(array_keys(CustomTripType::COLORS))],
            'mode' => ['required', Rule::enum(TripMode::class)],
            'accommodation' => ['nullable', Rule::enum(Accommodation::class)],
            'transports' => ['array'],
            'transports.*' => [Rule::enum(Transport::class)],
            'members' => ['array'],
            'members.*' => ['integer', Rule::exists('users', 'id')->where('active', true)],
            'cover' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:8192'],
            'remove_cover' => ['boolean'],
            'cover_focus_x' => ['nullable', 'integer', 'between:0,100'],
            'cover_focus_y' => ['nullable', 'integer', 'between:0,100'],
            'cover_zoom' => ['nullable', 'integer', 'between:100,'.self::MAX_COVER_ZOOM],
            'source_trip' => ['nullable', 'string', 'max:120'],
        ];
    }

    public function attributes(): array
    {
        return [
            'name' => 'Name',
            'location' => 'Reiseziel',
            'start_date' => 'Anreise',
            'end_date' => 'Abreise',
            'type' => 'Reiseart',
            'mode' => 'Art der Reise',
            'accommodation' => 'Unterkunft',
            'transports' => 'Anreise mit',
            'transports.*' => 'Anreise mit',
            'members' => 'Mitreisende',
            'cover' => 'Bild',
            'new_type_name' => 'Name der Reiseart',
            'new_type_icon' => 'Icon',
            'new_type_color' => 'Farbe',
        ];
    }

    public function messages(): array
    {
        return [
            'new_type_name.unique' => 'Eine Reiseart mit diesem Namen gibt es schon.',
            'new_type_name.required_if' => 'Bitte gib der neuen Reiseart einen Namen.',
            'new_type_icon.required_if' => 'Bitte wähle ein Icon.',
            'new_type_color.required_if' => 'Bitte wähle eine Farbe.',
        ];
    }

    public function after(): array
    {
        return [function (Validator $validator) {
            if ($validator->errors()->hasAny(['start_date', 'end_date'])) {
                return;
            }

            if (Carbon::parse($this->input('start_date'))->diffInDays(Carbon::parse($this->input('end_date'))) > 365) {
                $validator->errors()->add('end_date', 'Eine Reise darf höchstens ein Jahr dauern.');
            }
        }];
    }

    /** Validierte Daten; „neue Reiseart“ wird dabei angelegt und als „t:12“ eingesetzt. */
    public function tripData(): array
    {
        $data = $this->validated();

        if ($data['type'] === self::NEW_TYPE) {
            $type = $this->user()->customTripTypes()->create([
                'name' => trim($data['new_type_name']),
                'icon' => $data['new_type_icon'],
                'color' => $data['new_type_color'],
            ]);
            $data['type'] = $type->key();
        }

        return collect($data)->except(['new_type_name', 'new_type_icon', 'new_type_color'])->all();
    }

    /** @return list<int> */
    public function memberIds(): array
    {
        return array_map('intval', $this->input('members', []));
    }
}
