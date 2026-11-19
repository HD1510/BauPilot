<?php

use Illuminate\Support\Facades\Mail;

/**
 * Der Mail-Test muss ehrlich sein: Steht der Versand nur auf „log",
 * darf er nicht Erfolg melden.
 */
test('bei log-versand meldet der test klar, dass nichts zugestellt wird', function () {
    config(['mail.default' => 'log']);

    $this->artisan('baupilot:mail-test', ['to' => 'chef@example.at'])
        ->expectsOutputToContain('es wird nichts zugestellt')
        ->assertFailed();
});

test('mit echtem anbieter geht die testnachricht hinaus', function () {
    config(['mail.default' => 'smtp']);
    Mail::fake();

    $this->artisan('baupilot:mail-test', ['to' => 'chef@example.at'])
        ->expectsOutputToContain('Testnachricht an chef@example.at übergeben')
        ->assertSuccessful();
});

test('eine ungültige adresse wird abgelehnt', function () {
    $this->artisan('baupilot:mail-test', ['to' => 'keine-adresse'])
        ->assertFailed();
});
