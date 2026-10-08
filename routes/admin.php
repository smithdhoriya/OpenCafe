<?php

use Illuminate\Support\Facades\Route;
use Livewire\Volt\Volt;

Route::middleware(['auth', 'admin'])->group(function () {
    Volt::route('admin', 'admin.dashboard')->name('admin.dashboard');

    Route::prefix('admin')->name('admin.')->group(function () {
        Volt::route('orders', 'admin.orders.index')->name('orders');
        Volt::route('orders/{orderId}', 'admin.orders.show')->name('orders.show');
        Volt::route('menu', 'admin.menu')->name('menu');
        Volt::route('categories', 'admin.categories')->name('categories');
        Volt::route('customers', 'admin.customers.index')->name('customers');
        Volt::route('customers/{userId}', 'admin.customers.show')->name('customers.show');
        Volt::route('offers', 'admin.offers')->name('offers');
        Volt::route('reviews', 'admin.reviews')->name('reviews');
        Volt::route('settings', 'admin.settings')->name('settings');
        Volt::route('ai-assistant', 'admin.ai-assistant')->name('ai-assistant');
    });
});
