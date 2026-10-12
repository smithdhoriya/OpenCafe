<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote')->hourly();

Artisan::command('groq:diagnose', function () {
    $configured = (string) config('services.groq.key') !== '';

    $this->line('Groq API key configured: '.($configured ? 'yes' : 'no'));
    $this->line('Groq model: '.(string) config('services.groq.model'));
    $this->line('Groq base URL: '.(string) config('services.groq.base_url'));

    if (! $configured) {
        $this->warn('Set GROQ_API_KEY in your .env file (not .env.example), then run "php artisan config:clear".');
    }
})->purpose('Safely check whether the Groq API key is configured, without revealing it');
