<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;
use Throwable;

/**
 * Prüft den Mailversand nach dem Einrichten des Anbieters: schickt eine
 * Testnachricht und sagt klar, ob sie hinausging — oder warum nicht.
 */
class SendTestMail extends Command
{
    protected $signature = 'baupilot:mail-test {to : Empfänger-Adresse}';

    protected $description = 'Schickt eine Test-E-Mail über den konfigurierten Anbieter';

    public function handle(): int
    {
        $to = (string) $this->argument('to');
        $mailer = (string) config('mail.default');

        if (filter_var($to, FILTER_VALIDATE_EMAIL) === false) {
            $this->error("„{$to}“ ist keine gültige E-Mail-Adresse.");

            return self::FAILURE;
        }

        // log/array schreiben nur mit — nach außen geht dabei nichts.
        if (in_array($mailer, ['log', 'array'], true)) {
            $this->warn("MAIL_MAILER steht auf „{$mailer}“ — E-Mails landen nur im Log, es wird nichts zugestellt.");
            $this->line('In der .env MAIL_MAILER=smtp samt Zugangsdaten des Anbieters setzen (siehe .env.example).');

            return self::FAILURE;
        }

        try {
            Mail::raw(
                'Das ist eine Testnachricht von BauPilot. Wenn sie ankommt, funktioniert der Mailversand.',
                fn ($message) => $message->to($to)->subject('BauPilot: Test des Mailversands'),
            );
        } catch (Throwable $exception) {
            $this->error('Versand fehlgeschlagen: '.$exception->getMessage());

            return self::FAILURE;
        }

        $this->info("Testnachricht an {$to} übergeben (Anbieter: {$mailer}). Bitte Posteingang und Spam-Ordner prüfen.");

        return self::SUCCESS;
    }
}
