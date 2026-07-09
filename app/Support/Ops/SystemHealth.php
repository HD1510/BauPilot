<?php

namespace App\Support\Ops;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Betriebszustand für den erweiterten Health-Endpunkt (Architekturblatt
 * Abschnitt 10): Plattenfüllstand, Queue-Rückstau (Alter des ältesten
 * wartenden Jobs) und Zeitstempel des letzten Scheduler-Laufs. Ein
 * hängender Worker oder ausgefallener Cron fällt sonst erst auf, wenn
 * der tägliche Digest ausbleibt.
 */
class SystemHealth
{
    /**
     * Wird vom minütlichen Heartbeat in routes/console.php beschrieben.
     */
    public const string SCHEDULER_CACHE_KEY = 'baupilot:scheduler:last_run';

    /**
     * @return array{
     *     ok: bool,
     *     time: string,
     *     disk: array{used_percent: float|null, free_gb: float|null, total_gb: float|null, ok: bool},
     *     queue: array{waiting: int, oldest_waiting_seconds: int, ok: bool},
     *     scheduler: array{last_run: string|null, age_seconds: int|null, ok: bool},
     * }
     */
    public function report(): array
    {
        $disk = $this->disk();
        $queue = $this->queue();
        $scheduler = $this->scheduler();

        return [
            'ok' => $disk['ok'] && $queue['ok'] && $scheduler['ok'],
            'time' => now()->toIso8601String(),
            'disk' => $disk,
            'queue' => $queue,
            'scheduler' => $scheduler,
        ];
    }

    /**
     * @return array{used_percent: float|null, free_gb: float|null, total_gb: float|null, ok: bool}
     */
    private function disk(): array
    {
        $path = storage_path();
        $free = disk_free_space($path);
        $total = disk_total_space($path);

        if ($free === false || $total === false || $total <= 0.0) {
            return ['used_percent' => null, 'free_gb' => null, 'total_gb' => null, 'ok' => false];
        }

        $usedPercent = round((1 - $free / $total) * 100, 1);

        return [
            'used_percent' => $usedPercent,
            'free_gb' => round($free / 1024 ** 3, 1),
            'total_gb' => round($total / 1024 ** 3, 1),
            'ok' => $usedPercent < (int) config('monitoring.disk_warn_used_percent'),
        ];
    }

    /**
     * @return array{waiting: int, oldest_waiting_seconds: int, ok: bool}
     */
    private function queue(): array
    {
        // Datenbank-Queue-Treiber (Abschnitt 9): wartend = noch nicht von
        // einem Worker reserviert. Verzögerte Jobs (available_at in der
        // Zukunft) zählen nicht als Rückstau.
        $waiting = DB::table('jobs')->whereNull('reserved_at')->count();

        $oldestAvailableAt = DB::table('jobs')
            ->whereNull('reserved_at')
            ->where('available_at', '<=', now()->getTimestamp())
            ->min('available_at');

        $ageSeconds = $oldestAvailableAt === null
            ? 0
            : max(0, now()->getTimestamp() - (int) $oldestAvailableAt);

        return [
            'waiting' => $waiting,
            'oldest_waiting_seconds' => $ageSeconds,
            'ok' => $ageSeconds <= (int) config('monitoring.queue_max_age_minutes') * 60,
        ];
    }

    /**
     * @return array{last_run: string|null, age_seconds: int|null, ok: bool}
     */
    private function scheduler(): array
    {
        $lastRun = Cache::get(self::SCHEDULER_CACHE_KEY);

        if (! is_string($lastRun) || $lastRun === '') {
            // Nie gelaufen (oder Cache geleert): genau der Fall, den der
            // Uptime-Check melden soll — ein ausgefallener Cron.
            return ['last_run' => null, 'age_seconds' => null, 'ok' => false];
        }

        $ageSeconds = (int) CarbonImmutable::parse($lastRun)->diffInSeconds(now(), absolute: true);

        return [
            'last_run' => $lastRun,
            'age_seconds' => $ageSeconds,
            'ok' => $ageSeconds <= (int) config('monitoring.scheduler_max_age_minutes') * 60,
        ];
    }
}
