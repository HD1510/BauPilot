<?php

use App\Enums\CompanyRole;
use App\Mail\DailyDigestMail;
use App\Models\Company;
use App\Models\NotificationLogEntry;
use App\Models\NotificationSetting;
use App\Models\OutgoingInvoice;
use App\Models\User;
use App\Support\Tenancy\CompanyContext;
use Illuminate\Support\Facades\Mail;

afterEach(function () {
    app(CompanyContext::class)->clear();
});

function digestCompany(): array
{
    $company = Company::factory()->create();
    $user = User::factory()->create();
    $company->users()->attach($user->id, ['role' => CompanyRole::Office->value]);

    // Eine überfällige Rechnung — es steht etwas an
    OutgoingInvoice::factory()->create([
        'company_id' => $company->id,
        'due_on' => now()->subDays(3)->toDateString(),
    ]);

    return [$company, $user];
}

test('digest kommt genau einmal je benutzer, firma und tag', function () {
    Mail::fake();
    [$company, $user] = digestCompany();

    $this->artisan('baupilot:daily-digest')->assertSuccessful();
    $this->artisan('baupilot:daily-digest')->assertSuccessful();

    Mail::assertSent(DailyDigestMail::class, 1);
    Mail::assertSent(DailyDigestMail::class, fn (DailyDigestMail $mail) => $mail->hasTo($user->email));

    expect(NotificationLogEntry::query()
        ->where('company_id', $company->id)
        ->where('user_id', $user->id)
        ->where('kind', 'daily_digest')
        ->count())->toBe(1);
});

test('kein digest ohne anstehende fristen', function () {
    Mail::fake();

    $company = Company::factory()->create();
    $user = User::factory()->create();
    $company->users()->attach($user->id, ['role' => CompanyRole::Office->value]);

    $this->artisan('baupilot:daily-digest')->assertSuccessful();

    Mail::assertNothingSent();
});

test('abbestellt heißt abbestellt', function () {
    Mail::fake();
    [$company, $user] = digestCompany();

    NotificationSetting::create([
        'user_id' => $user->id,
        'company_id' => $company->id,
        'daily_email' => false,
    ]);

    $this->artisan('baupilot:daily-digest')->assertSuccessful();

    Mail::assertNothingSent();
});

test('rolle baustelle bekommt keinen digest über rein finanzielle fristen', function () {
    Mail::fake();
    [$company] = digestCompany(); // nur überfällige Rechnung vorhanden

    $siteUser = User::factory()->create();
    $company->users()->attach($siteUser->id, ['role' => CompanyRole::Site->value]);

    $this->artisan('baupilot:daily-digest')->assertSuccessful();

    // Büro-Benutzer erhält den Digest, Baustelle nicht
    Mail::assertSent(DailyDigestMail::class, 1);
    Mail::assertNotSent(DailyDigestMail::class, fn (DailyDigestMail $mail) => $mail->hasTo($siteUser->email));
});

test('digest je firma getrennt: zwei firmen, zwei mails an denselben benutzer', function () {
    Mail::fake();
    [$companyA, $user] = digestCompany();

    $companyB = Company::factory()->create();
    $companyB->users()->attach($user->id, ['role' => CompanyRole::Admin->value]);
    OutgoingInvoice::factory()->create([
        'company_id' => $companyB->id,
        'due_on' => now()->subDay()->toDateString(),
    ]);

    $this->artisan('baupilot:daily-digest')->assertSuccessful();

    Mail::assertSent(DailyDigestMail::class, 2);
});
