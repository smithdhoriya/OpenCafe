<?php

use App\Models\Combo;
use App\Models\MenuItem;
use App\Models\Offer;

test('the offers and combos page shows an honest empty state when none exist', function () {
    expect(Offer::count())->toBe(0);
    expect(Combo::count())->toBe(0);

    $this->get(route('offers.index'))
        ->assertOk()
        ->assertSee('No active offers or combos right now');
});

test('a real active offer is shown on the customer offers page', function () {
    Offer::factory()->create(['name' => 'Weekend Treat', 'code' => 'WEEKEND10', 'is_active' => true]);

    $this->get(route('offers.index'))
        ->assertOk()
        ->assertSee('Weekend Treat')
        ->assertSee('WEEKEND10');
});

test('an inactive offer is not shown on the customer offers page', function () {
    Offer::factory()->create(['name' => 'Hidden Offer', 'is_active' => false]);

    $this->get(route('offers.index'))
        ->assertOk()
        ->assertDontSee('Hidden Offer');
});

test('an expired offer is not shown even if it is still marked active', function () {
    Offer::factory()->create([
        'name' => 'Expired Offer',
        'is_active' => true,
        'ends_at' => now()->subDay(),
    ]);

    $this->get(route('offers.index'))
        ->assertOk()
        ->assertDontSee('Expired Offer');
});

test('a real available combo is shown with its items on the customer offers page', function () {
    $menuItem = MenuItem::factory()->create(['name' => 'Iced Latte']);
    $combo = Combo::factory()->create(['name' => 'Morning Combo', 'is_available' => true]);
    $combo->menuItems()->attach($menuItem->id, ['quantity' => 1]);

    $this->get(route('offers.index'))
        ->assertOk()
        ->assertSee('Morning Combo')
        ->assertSee('Iced Latte')
        ->assertSee('Add to cart');
});

test('an unavailable combo is not shown on the customer offers page', function () {
    Combo::factory()->unavailable()->create(['name' => 'Hidden Combo']);

    $this->get(route('offers.index'))
        ->assertOk()
        ->assertDontSee('Hidden Combo');
});

test('a combo scheduled to start in the future is not shown even if marked available', function () {
    Combo::factory()->create([
        'name' => 'Future Combo',
        'is_available' => true,
        'starts_at' => now()->addWeek(),
    ]);

    $this->get(route('offers.index'))
        ->assertOk()
        ->assertDontSee('Future Combo');
});
