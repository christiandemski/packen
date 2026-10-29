<?php

namespace App\Http\Requests;

use App\Enums\WeatherNeed;
use App\Models\Category;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ItemRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Besitz prüft der Controller über die ItemPolicy.
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'name' => trim((string) $this->input('name')),
            'per_day' => $this->boolean('per_day'),
            'pack_last' => $this->boolean('pack_last'),
        ]);

        // Schnelleingabe im Katalog schickt keine Häkchen – dann nach dem Namen vorbelegen (siehe Item).
        foreach (WeatherNeed::cases() as $need) {
            if (! $this->expectsJson() || $this->has($need->column())) {
                $this->merge([$need->column() => $this->boolean($need->column())]);
            }
        }
    }

    public function rules(): array
    {
        return [
            'name' => [
                'required', 'string', 'max:120',
                Rule::unique('items', 'name')->where('owner_id', $this->user()->id)->ignore($this->route('item')),
            ],
            'category' => ['required', 'string', Rule::in(Category::choices($this->user())->pluck('key'))],
            'weight_g' => ['nullable', 'integer', 'min:0', 'max:100000'],
            'per_day' => ['boolean'],
            'pack_last' => ['boolean'],
            'rain_protection' => ['boolean'],
            'cold_protection' => ['boolean'],
            'sun_protection' => ['boolean'],
            'notes' => ['nullable', 'string', 'max:500'],
            'default_container_id' => ['nullable', 'integer', Rule::exists('containers', 'id')->where('owner_id', $this->user()->id)],
        ];
    }

    /** „category“ kann eine eigene Kategorie sein („c:12“) – daraus werden die beiden Spalten. */
    public function validated($key = null, $default = null)
    {
        $data = parent::validated();
        $data = [...$data, ...Category::columnsFor($data['category'], $this->user())];

        return data_get($data, $key, $default);
    }

    public function attributes(): array
    {
        return [
            'category' => 'Kategorie',
            'weight_g' => 'Gewicht',
            'notes' => 'Notiz',
            'default_container_id' => 'Standardplatz',
        ];
    }

    public function messages(): array
    {
        return ['name.unique' => 'Diesen Gegenstand gibt es schon in deinem Katalog.'];
    }
}
