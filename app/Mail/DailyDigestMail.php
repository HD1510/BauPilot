<?php

namespace App\Mail;

use App\Models\Company;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/**
 * Tägliche Zusammenfassung (Architekturblatt Abschnitt 7): kommt nur,
 * wenn laut DeadlineService etwas ansteht, und je Benutzer, Firma und Tag
 * genau einmal (notification_log).
 */
class DailyDigestMail extends Mailable
{
    /**
     * @param  array<int, array<string, mixed>>  $deadlines
     */
    public function __construct(
        public Company $company,
        public array $deadlines,
        public int $overdueCount,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "BauPilot {$this->company->short_code}: "
                .count($this->deadlines).' Fristen'
                .($this->overdueCount > 0 ? ", {$this->overdueCount} überfällig" : ''),
        );
    }

    public function content(): Content
    {
        return new Content(text: 'mail.daily-digest');
    }
}
