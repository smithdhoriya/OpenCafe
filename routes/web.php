<?php

use Illuminate\Support\Facades\Route;
use Livewire\Volt\Volt;

require __DIR__.'/customer.php';
require __DIR__.'/admin.php';

Route::view('dashboard', 'dashboard')
    ->middleware(['auth', 'verified'])
    ->name('dashboard');

Route::middleware(['auth'])->group(function () {
    Route::redirect('settings', 'settings/profile');

    Volt::route('settings/profile', 'settings.profile')->name('settings.profile');
    Volt::route('settings/password', 'settings.password')->name('settings.password');
    Volt::route('settings/appearance', 'settings.appearance')->name('settings.appearance');

    Volt::route('account/profile', 'customer.account.profile')->name('account.profile');
    Volt::route('account/addresses', 'customer.addresses.index')->name('addresses.index');
    Volt::route('account/orders', 'customer.orders.index')->name('orders.index');

    Volt::route('checkout', 'customer.checkout.index')->name('checkout.index');

    Volt::route('orders/{orderId}', 'customer.orders.show')->name('orders.show');
});

require __DIR__.'/auth.php';
