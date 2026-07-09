<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/**
 * Benachrichtigungswahl je Benutzer und Firma (Architekturblatt 4.3).
 * Ohne Eintrag gilt: tägliche E-Mail an, Push aus.
 *
 * @property int $id
 * @property int $user_id
 * @property int $company_id
 * @property bool $daily_email
 * @property bool $push
 */
#[Fillable(['user_id', 'company_id', 'daily_email', 'push'])]
class NotificationSetting extends Model
{
    protected function casts(): array
    {
        return [
            'daily_email' => 'boolean',
            'push' => 'boolean',
        ];
    }
}
