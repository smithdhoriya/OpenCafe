<?php

use App\Enums\OfferType;
use App\Models\Offer;
use App\Models\User;
use Livewire\Volt\Volt;

test('an admin can view the offers and combos page', function () {
    $admin = User::factory()->admin()->create();
    $offer = Offer::factory()->create(['name' => 'Festive Discount', 'code' => 'FEST10']);

    $this->actingAs($admin)
        ->get(route('admin.offers'))
        ->assertOk()
        ->assertSee('Festive Discount')
        ->assertSee('FEST10');
});

test('a customer cannot access admin offer management', function () {
    $customer = User::factory()->create();

    $this->actingAs($customer)
        ->get(route('admin.offers'))
        ->assertForbidden();
});

test('a staff user cannot access admin offer management', function () {
    $staff = User::factory()->staff()->create();

    $this->actingAs($staff)
        ->get(route('admin.offers'))
        ->assertForbidden();
});

test('a guest is redirected to login from admin offer management', function () {
    $this->get(route('admin.offers'))
        ->assertRedirect(route('login'));
});

test('an admin can create a valid percentage offer', function () {
    $admin = User::factory()->admin()->create();

    Volt::actingAs($admin)->test('admin.offers')
        ->call('startCreateOffer')
        ->set('offer_code', 'newyear20')
        ->set('offer_name', 'New Year Sale')
        ->set('offer_type', 'percentage')
        ->set('offer_value', '20')
        ->call('saveOffer')
        ->assertHasNoErrors();

    $offer = Offer::where('code', 'NEWYEAR20')->first();
    expect($offer)->not->toBeNull();
    expect($offer->name)->toBe('New Year Sale');
    expect($offer->type)->toBe(OfferType::Percentage);
    expect((float) $offer->value)->toBe(20.0);
    expect($offer->times_used)->toBe(0);
});

test('an invalid offer is rejected by validation', function () {
    $admin = User::factory()->admin()->create();

    Volt::actingAs($admin)->test('admin.offers')
        ->call('startCreateOffer')
        ->set('offer_code', '')
        ->set('offer_name', '')
        ->set('offer_value', '')
        ->call('saveOffer')
        ->assertHasErrors(['offer_code', 'offer_name', 'offer_value']);

    expect(Offer::count())->toBe(0);
});

test('a percentage offer value over 100 is rejected', function () {
    $admin = User::factory()->admin()->create();

    Volt::actingAs($admin)->test('admin.offers')
        ->call('startCreateOffer')
        ->set('offer_code', 'TOOBIG')
        ->set('offer_name', 'Too Big')
        ->set('offer_type', 'percentage')
        ->set('offer_value', '150')
        ->call('saveOffer')
        ->assertHasErrors(['offer_value']);
});

test('duplicate offer codes are rejected', function () {
    $admin = User::factory()->admin()->create();
    Offer::factory()->create(['code' => 'DUPE']);

    Volt::actingAs($admin)->test('admin.offers')
        ->call('startCreateOffer')
        ->set('offer_code', 'dupe')
        ->set('offer_name', 'Duplicate')
        ->set('offer_value', '10')
        ->call('saveOffer')
        ->assertHasErrors(['offer_code']);
});

test('an end date before the start date is rejected', function () {
    $admin = User::factory()->admin()->create();

    Volt::actingAs($admin)->test('admin.offers')
        ->call('startCreateOffer')
        ->set('offer_code', 'BADRANGE')
        ->set('offer_name', 'Bad Range')
        ->set('offer_value', '10')
        ->set('offer_starts_at', '2026-06-10T00:00')
        ->set('offer_ends_at', '2026-06-01T00:00')
        ->call('saveOffer')
        ->assertHasErrors(['offer_ends_at']);

    expect(Offer::count())->toBe(0);
});

test('an admin can edit an existing offer', function () {
    $admin = User::factory()->admin()->create();
    $offer = Offer::factory()->create(['name' => 'Old Name', 'value' => 10]);

    Volt::actingAs($admin)->test('admin.offers')
        ->call('startEditOffer', $offer->id)
        ->set('offer_name', 'New Name')
        ->set('offer_value', '15')
        ->call('saveOffer')
        ->assertHasNoErrors();

    $offer->refresh();
    expect($offer->name)->toBe('New Name');
    expect((float) $offer->value)->toBe(15.0);
});

test('editing an offer keeps its own code valid against the uniqueness rule', function () {
    $admin = User::factory()->admin()->create();
    $offer = Offer::factory()->create(['code' => 'KEEPME']);

    Volt::actingAs($admin)->test('admin.offers')
        ->call('startEditOffer', $offer->id)
        ->set('offer_name', 'Updated Name')
        ->call('saveOffer')
        ->assertHasNoErrors();

    expect($offer->refresh()->code)->toBe('KEEPME');
});

test('an admin can activate and deactivate an offer', function () {
    $admin = User::factory()->admin()->create();
    $offer = Offer::factory()->create(['is_active' => true]);

    Volt::actingAs($admin)->test('admin.offers')
        ->call('toggleOfferActive', $offer->id);

    expect($offer->refresh()->is_active)->toBeFalse();

    Volt::actingAs($admin)->test('admin.offers')
        ->call('toggleOfferActive', $offer->id);

    expect($offer->refresh()->is_active)->toBeTrue();
});

test('an admin can delete an offer', function () {
    $admin = User::factory()->admin()->create();
    $offer = Offer::factory()->create();

    Volt::actingAs($admin)->test('admin.offers')
        ->call('deleteOffer', $offer->id);

    expect(Offer::query()->whereKey($offer->id)->exists())->toBeFalse();
});

test('an admin can search offers by name or code', function () {
    $admin = User::factory()->admin()->create();
    Offer::factory()->create(['name' => 'Summer Special', 'code' => 'SUMMER1']);
    Offer::factory()->create(['name' => 'Winter Deal', 'code' => 'WINTER1']);

    Volt::actingAs($admin)->test('admin.offers')
        ->set('offerSearch', 'Summer')
        ->assertSee('Summer Special')
        ->assertDontSee('Winter Deal');
});

test('an admin can filter offers by active status', function () {
    $admin = User::factory()->admin()->create();
    Offer::factory()->create(['name' => 'Active One', 'is_active' => true]);
    Offer::factory()->create(['name' => 'Inactive One', 'is_active' => false]);

    Volt::actingAs($admin)->test('admin.offers')
        ->set('offerStatusFilter', 'inactive')
        ->assertSee('Inactive One')
        ->assertDontSee('Active One');
});
