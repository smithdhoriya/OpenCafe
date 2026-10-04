<?php

use Livewire\Volt\Volt;

Volt::route('/', 'customer.home')->name('home');

Volt::route('menu', 'customer.menu.index')->name('menu.index');
Volt::route('menu/{menuItem:slug}', 'customer.menu.show')->name('menu.show');

Volt::route('offers', 'customer.offers.index')->name('offers.index');

Volt::route('about', 'customer.about')->name('about');
Volt::route('contact', 'customer.contact')->name('contact');

Volt::route('cart', 'customer.cart.index')->name('cart.index');
