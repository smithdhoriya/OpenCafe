<?php

use App\Models\Combo;
use App\Models\Offer;
use App\Models\User;
use Livewire\Volt\Volt;

// Authorization for /admin/offers (admin allowed, customer/staff forbidden,
// guest redirected) is covered by AdminOfferManagementTest and
// AdminComboManagementTest. This file only guards the "no fake data" invariant
// now that the page is a real CRUD UI rather than a static placeholder.

test('the admin offers tab honestly shows nothing when there is no real data, without inventing any', function () {
    $admin = User::factory()->admin()->create();

    expect(Offer::count())->toBe(0);

    $this->actingAs($admin)
        ->get(route('admin.offers'))
        ->assertOk()
        ->assertSee('No offers match your filters.');

    expect(Offer::count())->toBe(0);
});

test('the admin combos tab honestly shows nothing when there is no real data, without inventing any', function () {
    $admin = User::factory()->admin()->create();

    expect(Combo::count())->toBe(0);

    Volt::actingAs($admin)->test('admin.offers')
        ->set('activeTab', 'combos')
        ->assertSee('No combos match your filters.');

    expect(Combo::count())->toBe(0);
});
