<?php

namespace App\Models;

use App\Enums\ContainerKind;
use App\Models\Concerns\OwnedByUser;
use Database\Factories\ContainerFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Container extends Model
{
    /** @use HasFactory<ContainerFactory> */
    use HasFactory, OwnedByUser;

    protected $fillable = ['owner_id', 'name', 'kind', 'capacity_l', 'max_weight_g', 'empty_weight_g'];

    protected function casts(): array
    {
        return [
            'kind' => ContainerKind::class,
            'capacity_l' => 'integer',
            'max_weight_g' => 'integer',
            'empty_weight_g' => 'integer',
        ];
    }

    /** z. B. „60 l · max. 23 kg“ */
    public function limitsLabel(): ?string
    {
        $parts = array_filter([
            $this->capacity_l ? $this->capacity_l.' l' : null,
            $this->max_weight_g ? 'max. '.self::kg($this->max_weight_g) : null,
        ]);

        return $parts ? implode(' · ', $parts) : null;
    }

    public static function kg(int $grams): string
    {
        return $grams >= 1000 ? number_format($grams / 1000, 1, ',', '.').' kg' : $grams.' g';
    }
}
