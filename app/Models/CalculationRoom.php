<?php

namespace App\Models;

use App\Enums\RoomMaterial;
use App\Enums\RoomShape;
use App\Models\Concerns\BelongsToCompany;
use App\Models\Concerns\TracksUserStamps;
use Database\Factories\CalculationRoomFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Raum einer Baukalkulation: Form, Maße, Belag und Abzüge (Türen,
 * Öffnungen) — die Mengen rechnet der RoomCalculator.
 *
 * @property int $id
 * @property int $company_id
 * @property int $calculation_id
 * @property string $name
 * @property RoomShape $shape
 * @property RoomMaterial $material
 * @property numeric-string|null $length
 * @property numeric-string|null $width
 * @property numeric-string $height
 * @property numeric-string|null $length2
 * @property numeric-string|null $width2
 * @property numeric-string|null $depth
 * @property numeric-string|null $area_manual
 * @property numeric-string|null $perimeter_manual
 * @property int $edges
 * @property numeric-string $door_width
 * @property bool $estimated
 */
#[Fillable([
    'calculation_id',
    'name',
    'shape',
    'material',
    'length',
    'width',
    'height',
    'length2',
    'width2',
    'depth',
    'area_manual',
    'perimeter_manual',
    'edges',
    'door_width',
    'estimated',
])]
class CalculationRoom extends Model
{
    /** @use HasFactory<CalculationRoomFactory> */
    use BelongsToCompany, HasFactory, TracksUserStamps;

    protected function casts(): array
    {
        return [
            'shape' => RoomShape::class,
            'material' => RoomMaterial::class,
            'length' => 'decimal:2',
            'width' => 'decimal:2',
            'height' => 'decimal:2',
            'length2' => 'decimal:2',
            'width2' => 'decimal:2',
            'depth' => 'decimal:2',
            'area_manual' => 'decimal:2',
            'perimeter_manual' => 'decimal:2',
            'edges' => 'integer',
            'door_width' => 'decimal:2',
            'estimated' => 'boolean',
        ];
    }

    /** @return BelongsTo<Calculation, $this> */
    public function calculation(): BelongsTo
    {
        return $this->belongsTo(Calculation::class);
    }
}
