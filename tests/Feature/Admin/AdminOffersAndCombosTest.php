<?php

use App\Models\Combo;
use App\Models\Offer;
use App\Models\User;
use Illuminate\Support\Facades\DB;
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

test('the active offers count only includes offers that are flagged active and within their date window', function () {
    $admin = User::factory()->admin()->create();

    Offer::factory()->create(['is_active' => true, 'starts_at' => null, 'ends_at' => null]);
    Offer::factory()->create(['is_active' => true, 'starts_at' => now()->subDay(), 'ends_at' => now()->addDay()]);
    Offer::factory()->create(['is_active' => false, 'starts_at' => null, 'ends_at' => null]);
    Offer::factory()->create(['is_active' => true, 'starts_at' => now()->addDay(), 'ends_at' => null]);
    Offer::factory()->create(['is_active' => true, 'starts_at' => null, 'ends_at' => now()->subDay()]);

    $component = Volt::actingAs($admin)->test('admin.offers');

    expect($component->get('activeOffersCount'))->toBe(2);
});

test('the active combos count only includes combos that are flagged available and within their date window', function () {
    $admin = User::factory()->admin()->create();

    Combo::factory()->create(['is_available' => true, 'starts_at' => null, 'ends_at' => null]);
    Combo::factory()->create(['is_available' => true, 'starts_at' => now()->subDay(), 'ends_at' => now()->addDay()]);
    Combo::factory()->create(['is_available' => false, 'starts_at' => null, 'ends_at' => null]);
    Combo::factory()->create(['is_available' => true, 'starts_at' => now()->addDay(), 'ends_at' => null]);
    Combo::factory()->create(['is_available' => true, 'starts_at' => null, 'ends_at' => now()->subDay()]);

    $component = Volt::actingAs($admin)->test('admin.offers');

    expect($component->get('activeCombosCount'))->toBe(2);
});

test('the active offers and combos counts are computed with a database-side count, not by loading every row', function () {
    $admin = User::factory()->admin()->create();

    Offer::factory()->count(5)->create(['is_active' => true, 'starts_at' => null, 'ends_at' => null]);
    Combo::factory()->count(5)->create(['is_available' => true, 'starts_at' => null, 'ends_at' => null]);

    // The computed properties are cached per component instance and are
    // already evaluated once during the initial render (the stat cards show
    // them), so the listener must be attached before that first render —
    // not after — to observe how the count is actually produced.
    $countQueries = [];
    DB::listen(function ($query) use (&$countQueries) {
        if (preg_match('/select count.*from\s+["`]?(offers|combos)["`]?/i', $query->sql)) {
            $countQueries[] = $query->sql;
        }
    });

    Volt::actingAs($admin)->test('admin.offers');

    // At least one aggregate COUNT query per card (the paginated offers list
    // also runs its own internal COUNT, which is fine and not what's being
    // checked here) — never a bare, unconstrained "select * from offers" /
    // "select * from combos" fetching every row, which is exactly the
    // Offer::all()/Combo::all() pattern being fixed.
    expect($countQueries)->toHaveCount(3)
        ->and(collect($countQueries)->filter(fn ($sql) => str_contains($sql, 'offers')))->toHaveCount(2)
        ->and(collect($countQueries)->filter(fn ($sql) => str_contains($sql, 'combos')))->toHaveCount(1);
});
