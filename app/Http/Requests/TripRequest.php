<?php

namespace App\Http\Requests;

use App\Enums\Accommodation;
use App\Enums\Transport;
use App\Enums\TripMode;
use App\Enums\TripType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class TripRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Rechte prüft der Controller über die TripPolicy.
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:120'],
            'location' => ['nullable', 'string', 'max:120'],
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after_or_equal:start_date'],
            'type' => ['required', Rule::enum(TripType::class)],
            'mode' => ['required', Rule::enum(TripMode::class)],
            'accommodation' => ['nullable', Rule::enum(Accommodation::class)],
            'transport' => ['nullable', Rule::enum(Transport::class)],
            'members' => ['array'],
            'members.*' => ['integer', Rule::exists('users', 'id')->where('active', true)],
            'cover' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:8192'],
            'remove_cover' => ['boolean'],
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
            'mode' => 'Art der Liste',
            'accommodation' => 'Unterkunft',
            'transport' => 'Anreise mit',
            'members' => 'Mitreisende',
            'cover' => 'Bild',
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

    /** @return list<int> */
    public function memberIds(): array
    {
        return array_map('intval', $this->input('members', []));
    }
}
