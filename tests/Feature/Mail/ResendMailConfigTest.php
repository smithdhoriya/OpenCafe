<?php

use Illuminate\Mail\Transport\ResendTransport;
use Illuminate\Support\Facades\Mail;

test('the resend mailer resolves to the Resend transport using the configured services key', function () {
    config()->set('services.resend.key', 'test-fake-key-not-a-real-secret');
    config()->set('mail.default', 'resend');

    $transport = Mail::mailer('resend')->getSymfonyTransport();

    expect($transport)->toBeInstanceOf(ResendTransport::class);
});

test('services.resend.key is read from the RESEND_API_KEY environment variable', function () {
    $fakeKey = 'unit-test-fake-key-'.uniqid();

    putenv("RESEND_API_KEY={$fakeKey}");
    $_ENV['RESEND_API_KEY'] = $fakeKey;
    $_SERVER['RESEND_API_KEY'] = $fakeKey;

    $services = require config_path('services.php');

    putenv('RESEND_API_KEY');
    unset($_ENV['RESEND_API_KEY'], $_SERVER['RESEND_API_KEY']);

    expect($services['resend']['key'])->toBe($fakeKey);
});
