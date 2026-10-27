<?php

namespace App\Services;

use App\Enums\ItemCategory as C;
use App\Models\Item;
use App\Models\Template;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/** Start-Vorlagen, die beim ersten Login angeboten werden. */
class StarterTemplates
{
    /**
     * Gegenstand: [Name, Kategorie, Menge, Flags]; Flags: 'per_day', 'pack_last'.
     *
     * @return array<string, array{description: string, items: list<array{0: string, 1: C, 2?: int, 3?: list<string>}>}>
     */
    public static function definitions(): array
    {
        return [
            'Basis' => [
                'description' => 'Was auf jede Reise mitkommt',
                'items' => [
                    ['Zahnbürste', C::Toiletries, 1, ['pack_last']],
                    ['Zahnpasta', C::Toiletries],
                    ['Deo', C::Toiletries],
                    ['Duschgel', C::Toiletries],
                    ['Shampoo', C::Toiletries],
                    ['Medikamente', C::Health, 1, ['pack_last']],
                    ['Pflaster', C::Health],
                    ['Handy-Ladekabel', C::Electronics, 1, ['pack_last']],
                    ['Powerbank', C::Electronics],
                    ['Kopfhörer', C::Electronics],
                    ['Personalausweis', C::Documents, 1, ['pack_last']],
                    ['Geldbörse', C::Documents, 1, ['pack_last']],
                    ['Krankenversicherungskarte', C::Documents],
                    ['Unterhosen', C::Underwear, 1, ['per_day']],
                    ['Socken', C::Underwear, 1, ['per_day']],
                    ['T-Shirt', C::Tops],
                    ['Pullover', C::Outerwear],
                    ['Jeans', C::Bottoms],
                    ['Schlafanzug', C::Sleep],
                ],
            ],
            'Städtetrip' => [
                'description' => 'Viel zu Fuß unterwegs, abends ausgehen',
                'items' => [
                    ['Tagesrucksack', C::Gear],
                    ['Bequeme Sneaker', C::Shoes],
                    ['Regenjacke', C::Outerwear],
                    ['Sonnenbrille', C::Accessories],
                    ['Trinkflasche', C::Gear],
                    ['Reiseadapter', C::Electronics],
                    ['Hemd oder Bluse', C::Tops],
                ],
            ],
            'Strand' => [
                'description' => 'Sonne, Meer und Lesen',
                'items' => [
                    ['Badesachen', C::Sportswear, 2],
                    ['Strandtuch', C::Gear],
                    ['Sonnencreme', C::Toiletries],
                    ['After-Sun', C::Toiletries],
                    ['Sonnenhut', C::Accessories],
                    ['Sonnenbrille', C::Accessories],
                    ['Flip-Flops', C::Shoes],
                    ['Shorts', C::Bottoms],
                    ['Buch', C::Misc],
                ],
            ],
            'Wandern' => [
                'description' => 'Tagestouren mit Rucksack',
                'items' => [
                    ['Wanderschuhe', C::Shoes],
                    ['Wandersocken', C::Underwear, 1, ['per_day']],
                    ['Funktionsshirt', C::Sportswear],
                    ['Wanderhose', C::Bottoms],
                    ['Fleecejacke', C::Outerwear],
                    ['Regenjacke', C::Outerwear],
                    ['Wanderrucksack', C::Gear],
                    ['Trinkflasche', C::Gear],
                    ['Stirnlampe', C::Gear],
                    ['Taschenmesser', C::Gear],
                    ['Erste-Hilfe-Set', C::Health],
                    ['Blasenpflaster', C::Health],
                ],
            ],
            'Bikepacking' => [
                'description' => 'Mehrtägig mit dem Rad, Übernachtung im Zelt',
                'items' => [
                    ['Helm', C::Gear],
                    ['Radhose', C::Sportswear, 2],
                    ['Radtrikot', C::Sportswear, 2],
                    ['Radschuhe', C::Shoes],
                    ['Windjacke', C::Outerwear],
                    ['Lenkertasche', C::Gear],
                    ['Satteltasche', C::Gear],
                    ['Ersatzschlauch', C::Gear],
                    ['Flickzeug', C::Gear],
                    ['Minipumpe', C::Gear],
                    ['Multitool', C::Gear],
                    ['Kettenöl', C::Gear],
                    ['Fahrradlichter', C::Electronics, 1, ['pack_last']],
                    ['Powerbank', C::Electronics],
                    ['Zelt', C::Sleep],
                    ['Schlafsack', C::Sleep],
                    ['Isomatte', C::Sleep],
                    ['Stirnlampe', C::Gear],
                    ['Trinkflasche', C::Gear, 2],
                ],
            ],
        ];
    }

    /**
     * Übernimmt die gewählten Vorlagen. Gegenstände mit gleichem Namen werden
     * wiederverwendet, damit z. B. die Trinkflasche nur einmal im Katalog steht.
     *
     * @param  list<string>  $names
     */
    public function install(User $user, array $names): void
    {
        $definitions = array_intersect_key(self::definitions(), array_flip($names));

        DB::transaction(function () use ($user, $definitions) {
            $catalog = Item::accessibleBy($user)->get()->keyBy(fn (Item $item) => Str::lower($item->name));

            foreach ($definitions as $name => $definition) {
                $template = Template::create([
                    'owner_id' => $user->id,
                    'name' => $this->uniqueTemplateName($user, $name),
                    'description' => $definition['description'],
                ]);

                foreach ($definition['items'] as $entry) {
                    [$itemName, $category] = $entry;
                    $flags = $entry[3] ?? [];

                    $item = $catalog[Str::lower($itemName)] ??= Item::create([
                        'owner_id' => $user->id,
                        'name' => $itemName,
                        'category' => $category,
                        'per_day' => in_array('per_day', $flags, true),
                        'pack_last' => in_array('pack_last', $flags, true),
                    ]);

                    $template->items()->syncWithoutDetaching([$item->id => ['quantity' => $entry[2] ?? 1]]);
                }
            }

            $user->forceFill(['starter_templates_at' => now()])->save();
        });
    }

    public function dismiss(User $user): void
    {
        $user->forceFill(['starter_templates_at' => now()])->save();
    }

    private function uniqueTemplateName(User $user, string $name): string
    {
        $candidate = $name;

        for ($i = 2; Template::accessibleBy($user)->where('name', $candidate)->exists(); $i++) {
            $candidate = "{$name} {$i}";
        }

        return $candidate;
    }
}
