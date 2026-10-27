<?php

namespace App\Http\Requests;

use App\Enums\ItemCategory;
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
    }

    public function rules(): array
    {
        return [
            'name' => [
                'required', 'string', 'max:120',
                Rule::unique('items', 'name')->where('owner_id', $this->user()->id)->ignore($this->route('item')),
            ],
            'category' => ['required', Rule::enum(ItemCategory::class)],
            'weight_g' => ['nullable', 'integer', 'min:0', 'max:100000'],
            'per_day' => ['boolean'],
            'pack_last' => ['boolean'],
            'notes' => ['nullable', 'string', 'max:500'],
        ];
    }

    public function attributes(): array
    {
        return [
            'category' => 'Kategorie',
            'weight_g' => 'Gewicht',
            'notes' => 'Notiz',
        ];
    }

    public function messages(): array
    {
        return ['name.unique' => 'Diesen Gegenstand gibt es schon in deinem Katalog.'];
    }
}
