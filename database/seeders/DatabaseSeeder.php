<?php

namespace Database\Seeders;

use App\Enums\Accommodation;
use App\Enums\Transport;
use App\Enums\TripMode;
use App\Enums\TripType;
use App\Models\User;
use App\Services\ContainerService;
use App\Services\PacklistService;
use App\Services\StarterTemplates;
use App\Services\TripService;
use Illuminate\Database\Seeder;

/** Beispieldaten für die Entwicklung. Admin-Login: admin@packen.test / packen-admin */
class DatabaseSeeder extends Seeder
{
    public function run(TripService $trips, StarterTemplates $starters, PacklistService $packlists): void
    {
        $admin = User::factory()->admin()->create([
            'name' => 'Christian',
            'email' => 'admin@packen.test',
            'password' => 'packen-admin',
        ]);
        $anna = User::factory()->create(['name' => 'Anna', 'email' => 'anna@packen.test']);
        $max = User::factory()->create(['name' => 'Max', 'email' => 'max@packen.test']);

        $starters->install($admin, array_keys(StarterTemplates::definitions()));

        $lisbon = $trips->create($admin, [
            'name' => 'Lissabon',
            'location' => 'Lissabon, Portugal',
            'start_date' => today()->addDays(12)->toDateString(),
            'end_date' => today()->addDays(16)->toDateString(),
            'type' => TripType::Citytrip->value,
            'mode' => TripMode::Group->value,
            'accommodation' => Accommodation::Apartment->value,
            'transport' => Transport::Plane->value,
        ], memberIds: [$anna->id, $max->id]);

        $trips->create($admin, [
            'name' => 'Bikepacking Schwarzwald',
            'location' => 'Freiburg im Breisgau',
            'start_date' => today()->addDays(40)->toDateString(),
            'end_date' => today()->addDays(43)->toDateString(),
            'type' => TripType::Bikepacking->value,
            'mode' => TripMode::Solo->value,
            'accommodation' => Accommodation::Camping->value,
            'transport' => Transport::Bike->value,
        ]);

        $trips->create($admin, [
            'name' => 'Berlin-Marathon',
            'location' => 'Berlin',
            'start_date' => today()->subDays(30)->toDateString(),
            'end_date' => today()->subDays(28)->toDateString(),
            'type' => TripType::RunningEvent->value,
            'mode' => TripMode::Solo->value,
            'accommodation' => Accommodation::Hotel->value,
            'transport' => Transport::Train->value,
        ]);

        $trips->create($anna, [
            'name' => 'Annas Wochenende',
            'location' => 'Hamburg',
            'start_date' => today()->addDays(5)->toDateString(),
            'end_date' => today()->addDays(6)->toDateString(),
            'type' => TripType::Citytrip->value,
            'mode' => TripMode::Solo->value,
        ]);

        // Lissabon wie im Entwurf: Basis + Städtetrip, ein paar Sachen schon gepackt.
        $packlists->setTemplates($lisbon, $admin, $admin->templates()->whereIn('name', ['Basis', 'Städtetrip'])->pluck('id')->all());
        $packlists->addEntry($lisbon, $admin, ['custom_name' => 'Pastéis-Rezept für Mama', 'group' => 'misc']);
        $lisbon->entries()->where('user_id', $admin->id)->inRandomOrder()->limit(8)->get()->each->setPacked(true);

        // Behältnisse: Fluggepäck für Lissabon, Eurobox für Touren mit dem Auto
        $suitcase = $admin->containers()->create(['name' => 'Großer Koffer', 'kind' => 'suitcase', 'capacity_l' => 80, 'max_weight_g' => 23000, 'empty_weight_g' => 4200]);
        $backpack = $admin->containers()->create(['name' => 'Handgepäck-Rucksack', 'kind' => 'backpack', 'capacity_l' => 30, 'max_weight_g' => 8000]);
        $admin->containers()->create(['name' => 'Eurobox Küche', 'kind' => 'box', 'capacity_l' => 64]);
        $admin->items()->whereIn('name', ['Handy-Ladekabel', 'Powerbank', 'Kopfhörer', 'Personalausweis', 'Geldbörse'])->update(['default_container_id' => $backpack->id]);
        app(ContainerService::class)->setForTrip($lisbon, $admin, [$suitcase->id, $backpack->id]);
    }
}
