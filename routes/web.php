<?php

use Illuminate\Support\Facades\Route;
use Livewire\Volt\Volt;

require __DIR__.'/customer.php';

Route::view('dashboard', 'dashboard')
    ->middleware(['auth', 'verified'])
    ->name('dashboard');

Route::middleware(['auth'])->group(function () {
    Route::redirect('settings', 'settings/profile');

    Volt::route('settings/profile', 'settings.profile')->name('settings.profile');
    Volt::route('settings/password', 'settings.password')->name('settings.password');
    Volt::route('settings/appearance', 'settings.appearance')->name('settings.appearance');

    Volt::route('account/addresses', 'customer.addresses.index')->name('addresses.index');

    Volt::route('checkout', 'customer.checkout.index')->name('checkout.index');
});

require __DIR__.'/auth.php';
