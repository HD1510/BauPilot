<?php

use App\Support\Ops\SystemHealth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

function heartbeat(): void
{
    Cache::put(SystemHealth::SCHEDULER_CACHE_KEY, now()->toIso8601String());
}

test('/up bleibt als einfacher uptime-ping erhalten', function () {
    $this->get('/up')->assertOk();
});

test('/up/details meldet platte, queue und scheduler', function () {
    heartbeat();
    // Plattenschwelle in Testumgebungen nicht raten — hier zählt die Struktur.
    config()->set('monitoring.disk_warn_used_percent', 100);

    $this->getJson('/up/details')
        ->assertOk()
        ->assertJsonPath('ok', true)
        ->assertJsonPath('queue.waiting', 0)
        ->assertJsonPath('scheduler.ok', true)
        ->assertJsonStructure([
            'ok', 'time',
            'disk' => ['used_percent', 'free_gb', 'total_gb', 'ok'],
            'queue' => ['waiting', 'oldest_waiting_seconds', 'ok'],
            'scheduler' => ['last_run', 'age_seconds', 'ok'],
        ]);
});

test('ohne scheduler-heartbeat ist der status 503 — ausgefallener cron', function () {
    Cache::forget(SystemHealth::SCHEDULER_CACHE_KEY);

    $this->getJson('/up/details')
        ->assertStatus(503)
        ->assertJsonPath('scheduler.ok', false)
        ->assertJsonPath('scheduler.last_run', null);
});

test('ein alter heartbeat ist genauso ein ausfall wie keiner', function () {
    Cache::put(SystemHealth::SCHEDULER_CACHE_KEY, now()->subHour()->toIso8601String());
    config()->set('monitoring.disk_warn_used_percent', 100);

    $this->getJson('/up/details')
        ->assertStatus(503)
        ->assertJsonPath('scheduler.ok', false);
});

test('queue-rückstau: ein lange wartender job macht den status 503', function () {
    heartbeat();
    config()->set('monitoring.disk_warn_used_percent', 100);

    DB::table('jobs')->insert([
        'queue' => 'default',
        'payload' => '{}',
        'attempts' => 0,
        'reserved_at' => null,
        'available_at' => now()->subHour()->getTimestamp(),
        'created_at' => now()->subHour()->getTimestamp(),
    ]);

    $this->getJson('/up/details')
        ->assertStatus(503)
        ->assertJsonPath('queue.waiting', 1)
        ->assertJsonPath('queue.ok', false);
});

test('verzögerte jobs (available_at in der zukunft) zählen nicht als rückstau', function () {
    heartbeat();
    config()->set('monitoring.disk_warn_used_percent', 100);

    DB::table('jobs')->insert([
        'queue' => 'default',
        'payload' => '{}',
        'attempts' => 0,
        'reserved_at' => null,
        'available_at' => now()->addHours(2)->getTimestamp(),
        'created_at' => now()->getTimestamp(),
    ]);

    $this->getJson('/up/details')
        ->assertOk()
        ->assertJsonPath('queue.ok', true)
        ->assertJsonPath('queue.oldest_waiting_seconds', 0);
});

test('mit gesetztem token sind die details geschützt', function () {
    heartbeat();
    config()->set('monitoring.disk_warn_used_percent', 100);
    config()->set('monitoring.health_token', 'geheim');

    $this->getJson('/up/details')->assertForbidden();
    $this->getJson('/up/details?token=falsch')->assertForbidden();
    $this->getJson('/up/details?token=geheim')->assertOk();
});
