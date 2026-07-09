<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/**
 * Schutz vor Doppelversand (Architekturblatt Abschnitt 5): hält fest,
 * welche Meldung welchem Benutzer an welchem Tag geschickt wurde.
 *
 * @property int $id
 * @property int $company_id
 * @property int $user_id
 * @property string|null $source_type
 * @property int|null $source_id
 * @property string $kind
 * @property CarbonImmutable $sent_on
 */
#[Fillable(['company_id', 'user_id', 'source_type', 'source_id', 'kind', 'sent_on'])]
class NotificationLogEntry extends Model
{
    protected $table = 'notification_log';

    protected function casts(): array
    {
        return [
            'sent_on' => 'date',
        ];
    }
}
