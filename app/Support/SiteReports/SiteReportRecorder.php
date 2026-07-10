<?php

namespace App\Support\SiteReports;

use App\Enums\SiteReportStatus;
use App\Models\Company;
use App\Models\Employee;
use App\Models\Project;
use App\Models\SiteReport;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use InvalidArgumentException;

/**
 * Regieberichte anlegen und unterschreiben (M9) — eine Quelle für Web
 * und JSON-API. Der Nummernkreis läuft je Firma fortlaufend und wird
 * transaktional vergeben; die Unterschrift sperrt den Bericht
 * (zustands-idempotent: erneutes Unterschreiben ändert nichts).
 */
class SiteReportRecorder
{
    /**
     * Bereits erfasst? (Idempotenz über client_uuid, Abschnitt 9)
     */
    public function findExisting(?string $clientUuid): ?SiteReport
    {
        if ($clientUuid === null || $clientUuid === '') {
            return null;
        }

        return SiteReport::query()->where('client_uuid', $clientUuid)->first();
    }

    /**
     * @param  array<int, array{employee_id: int, hours: float|string}>  $entries
     */
    public function create(
        Project $project,
        string $reportDate,
        ?string $bodyText,
        ?string $materialText,
        array $entries = [],
        ?string $clientUuid = null,
    ): SiteReport {
        return DB::transaction(function () use ($project, $reportDate, $bodyText, $materialText, $entries, $clientUuid): SiteReport {
            // Nummernkreis je Firma: die Firmenzeile sperren serialisiert
            // gleichzeitige Berichte (Postgres erlaubt kein FOR UPDATE auf
            // Aggregaten); der Unique-Index (company_id, number) ist das Netz.
            Company::query()->whereKey($project->company_id)->lockForUpdate()->first();

            $number = (int) SiteReport::query()->max('number') + 1;

            $report = new SiteReport([
                'project_id' => $project->id,
                'report_date' => $reportDate,
                'body_text' => $bodyText,
                'material_text' => $materialText,
                'client_uuid' => $clientUuid,
            ]);
            $report->forceFill(['number' => $number, 'status' => SiteReportStatus::Draft])->save();

            $this->syncEntries($report, $entries);

            return $report;
        });
    }

    /**
     * Stunden-Zeilen ersetzen — nur solange der Bericht Entwurf ist.
     *
     * @param  array<int, array{employee_id: int, hours: float|string}>  $entries
     */
    public function syncEntries(SiteReport $report, array $entries): void
    {
        if ($report->isSigned()) {
            throw new InvalidArgumentException('Der Bericht ist unterschrieben und gesperrt.');
        }

        $report->entries()->delete();

        foreach ($entries as $entry) {
            // Global Scope: fremde Mitarbeiter sind gar nie sichtbar.
            $employee = Employee::query()->whereKey((int) $entry['employee_id'])->firstOrFail();

            $report->entries()->create([
                'employee_id' => $employee->id,
                'hours' => $entry['hours'],
            ]);
        }
    }

    /**
     * Unterschrift (PNG als Data-URL) ablegen und den Bericht sperren.
     * Zustands-idempotent: ein bereits unterschriebener Bericht bleibt
     * unverändert — die Wiederholung aus dem Offline-Puffer ist ok.
     */
    public function sign(SiteReport $report, string $signatureDataUrl, User $user): SiteReport
    {
        if ($report->isSigned()) {
            return $report;
        }

        $png = $this->decodeSignature($signatureDataUrl);

        $path = sprintf(
            '%s/%d/site_report/%d/unterschrift-%s.png',
            app()->environment(),
            $report->company_id,
            $report->id,
            Str::uuid(),
        );

        Storage::disk('documents')->put($path, $png);

        $report->forceFill([
            'signature_path' => $path,
            'signed_at' => now(),
            'status' => SiteReportStatus::Signed,
            'updated_by' => $user->id,
        ])->save();

        return $report;
    }

    private function decodeSignature(string $dataUrl): string
    {
        if (! str_starts_with($dataUrl, 'data:image/png;base64,')) {
            throw new InvalidArgumentException('Unterschrift muss ein PNG (Data-URL) sein.');
        }

        $decoded = base64_decode(substr($dataUrl, strlen('data:image/png;base64,')), true);

        // 1x1 px wäre keine Unterschrift; 2 MB reichen für jede Signatur.
        if ($decoded === false || strlen($decoded) < 100 || strlen($decoded) > 2 * 1024 * 1024) {
            throw new InvalidArgumentException('Unterschrift ist keine gültige PNG-Datei.');
        }

        return $decoded;
    }
}
