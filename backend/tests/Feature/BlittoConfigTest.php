<?php

it('provides app settings with parity defaults', function () {
    expect(config('blitto.environment'))->toBe('local')
        ->and(config('blitto.allowed_email_domains'))->toBe('pdn.ac.lk')
        ->and(config('blitto.nipo_follow_up_interval_hours'))->toBe(24)
        ->and(config('blitto.document_store'))->toBe('local');
});

it('does not expose the removed FastAPI parity keys', function () {
    expect(config('blitto'))->not->toHaveKeys(['storage', 'sqlite_path']);
});

it('reads settings from BLITTODB_-prefixed env vars', function () {
    putenv('BLITTODB_NIPO_FOLLOW_UP_INTERVAL_HOURS=6');
    $_ENV['BLITTODB_NIPO_FOLLOW_UP_INTERVAL_HOURS'] = '6';

    $this->refreshApplication();

    expect(config('blitto.nipo_follow_up_interval_hours'))->toBe(6);
});

it('defaults the mail channel to laravel and reads the env override', function () {
    // The suite forces the in-memory adapter (phpunit.xml); clear it to observe
    // the default the application itself ships with.
    putenv('BLITTODB_MAIL_CHANNEL');
    unset($_ENV['BLITTODB_MAIL_CHANNEL'], $_SERVER['BLITTODB_MAIL_CHANNEL']);
    $this->refreshApplication();

    expect(config('blitto.mail_channel'))->toBe('laravel');

    putenv('BLITTODB_MAIL_CHANNEL=array');
    $_ENV['BLITTODB_MAIL_CHANNEL'] = 'array';
    $this->refreshApplication();

    expect(config('blitto.mail_channel'))->toBe('array');
});

afterEach(function () {
    putenv('BLITTODB_NIPO_FOLLOW_UP_INTERVAL_HOURS');
    unset($_ENV['BLITTODB_NIPO_FOLLOW_UP_INTERVAL_HOURS']);

    // Leave the suite-wide test adapter in place for every later test.
    putenv('BLITTODB_MAIL_CHANNEL=array');
    $_ENV['BLITTODB_MAIL_CHANNEL'] = 'array';
});
