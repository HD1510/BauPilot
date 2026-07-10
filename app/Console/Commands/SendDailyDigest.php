<?php

namespace App\Console\Commands;

use App\Mail\DailyDigestMail;
use App\Models\Company;
use App\Models\NotificationLogEntry;
use App\Models\NotificationSetting;
use App\Models\User;
use App\Support\Deadlines\DeadlineService;
use App\Support\Tenancy\CompanyContext;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\Mail;

/**
 * Tägliche Zusammenfassung um 06:00 Europe/Vienna (Architekturblatt
 * Abschnitt 7): je Benutzer und Firma, nur wenn etwas ansteht,
 * dedupliziert über notification_log. Der Mandantenkontext wird je Firma
 * explizit gesetzt (Abschnitt 3) — nie implizit.
 */
class SendDailyDigest extends Command
{
    protected $signature = 'baupilot:daily-digest';

    protected $description = 'Verschickt die tägliche Fristen-Zusammenfassung je Benutzer und Firma';

    private const DIGEST_HORIZON_DAYS = 7;

    public function handle(CompanyContext $context, DeadlineService $deadlines): int
    {
        $today = CarbonImmutable::parse(Date::today());
        $sent = 0;

        $companies = Company::query()->whereNull('archived_at')->get();

        foreach ($companies as $company) {
            $sent += $context->runFor($company, function () use ($company, $deadlines, $today): int {
                return $this->sendForCompany($company, $deadlines, $today);
            });
        }

        $this->info("Digest verschickt: {$sent}");

        return self::SUCCESS;
    }

    private function sendForCompany(Company $company, DeadlineService $deadlines, CarbonImmutable $today): int
    {
        $sent = 0;

        /** @var Collection<int, User> $users */
        $users = $company->users()->get();

        foreach ($users as $user) {
            $setting = NotificationSetting::query()
                ->where('user_id', $user->id)
                ->where('company_id', $company->id)
                ->first();

            // Ohne Eintrag gilt: tägliche E-Mail an.
            if ($setting !== null && ! $setting->daily_email) {
                continue;
            }

            // Genau einmal je Benutzer, Firma und Tag.
            $alreadySent = NotificationLogEntry::query()
                ->where('company_id', $company->id)
                ->where('user_id', $user->id)
                ->where('kind', 'daily_digest')
                ->whereDate('sent_on', $today)
                ->exists();

            if ($alreadySent) {
                continue;
            }

            $role = $user->roleIn($company);

            if ($role === null) {
                continue;
            }

            $items = $deadlines
                ->upcoming($today->addDays(self::DIGEST_HORIZON_DAYS), $role->canViewFinancials())
                // Aufgaben-Erinnerung an Zuständige (Abschnitt 7): Fristen
                // mit Zuständigem erhält nur dieser; alles ohne Zuständigen
                // geht wie bisher an alle.
                ->filter(fn ($deadline): bool => $deadline->assigneeUserId === null || $deadline->assigneeUserId === $user->id)
                ->map(fn ($deadline) => $deadline->toArray($today))
                ->values();

            // Nur wenn etwas ansteht.
            if ($items->isEmpty()) {
                continue;
            }

            Mail::to($user->email)->send(new DailyDigestMail(
                $company,
                $items->all(),
                $items->where('overdue', true)->count(),
            ));

            NotificationLogEntry::create([
                'company_id' => $company->id,
                'user_id' => $user->id,
                'kind' => 'daily_digest',
                'sent_on' => $today,
            ]);

            $sent++;
        }

        return $sent;
    }
}
