<?php

use App\Enums\ReviewStatus;
use App\Models\Review;
use App\Models\User;
use Livewire\Volt\Volt;

test('an admin can view the reviews list', function () {
    $admin = User::factory()->admin()->create();
    $review = Review::factory()->create(['title' => 'Great coffee!']);

    $this->actingAs($admin)
        ->get(route('admin.reviews'))
        ->assertOk()
        ->assertSee('Great coffee!');
});

test('a customer cannot access admin review moderation', function () {
    $customer = User::factory()->create();

    $this->actingAs($customer)
        ->get(route('admin.reviews'))
        ->assertForbidden();
});

test('a staff user cannot access admin review moderation', function () {
    $staff = User::factory()->staff()->create();

    $this->actingAs($staff)
        ->get(route('admin.reviews'))
        ->assertForbidden();
});

test('a guest is redirected to login from admin review moderation', function () {
    $this->get(route('admin.reviews'))
        ->assertRedirect(route('login'));
});

test('an admin can filter reviews by status', function () {
    $admin = User::factory()->admin()->create();
    Review::factory()->pending()->create(['title' => 'Pending Review']);
    Review::factory()->create(['title' => 'Approved Review']);

    Volt::actingAs($admin)->test('admin.reviews')
        ->set('statusFilter', ReviewStatus::Pending->value)
        ->assertSee('Pending Review')
        ->assertDontSee('Approved Review');
});

test('an admin can approve a pending review, making it visible', function () {
    $admin = User::factory()->admin()->create();
    $review = Review::factory()->pending()->create();

    Volt::actingAs($admin)->test('admin.reviews')
        ->call('approve', $review->id);

    $review->refresh();
    expect($review->status)->toBe(ReviewStatus::Approved);
    expect($review->is_visible)->toBeTrue();
    expect($review->moderated_by)->toBe($admin->id);
    expect($review->moderated_at)->not->toBeNull();
});

test('an admin can reject a review with an internal note, hiding it from customers', function () {
    $admin = User::factory()->admin()->create();
    $review = Review::factory()->create();

    Volt::actingAs($admin)->test('admin.reviews')
        ->call('startReject', $review->id)
        ->set('moderation_notes', 'Contains inappropriate language')
        ->call('confirmReject');

    $review->refresh();
    expect($review->status)->toBe(ReviewStatus::Rejected);
    expect($review->is_visible)->toBeFalse();
    expect($review->moderated_by)->toBe($admin->id);
    expect($review->moderation_notes)->toBe('Contains inappropriate language');
});

test('rejecting a review does not require a note', function () {
    $admin = User::factory()->admin()->create();
    $review = Review::factory()->create();

    Volt::actingAs($admin)->test('admin.reviews')
        ->call('startReject', $review->id)
        ->call('confirmReject')
        ->assertHasNoErrors();

    expect($review->refresh()->status)->toBe(ReviewStatus::Rejected);
});

test('a rejected review can later be approved by an admin', function () {
    $admin = User::factory()->admin()->create();
    $review = Review::factory()->rejected()->create();

    Volt::actingAs($admin)->test('admin.reviews')
        ->call('approve', $review->id);

    $review->refresh();
    expect($review->status)->toBe(ReviewStatus::Approved);
    expect($review->is_visible)->toBeTrue();
});

test('a pending, approved, and rejected review each render a visibly different badge color', function () {
    $admin = User::factory()->admin()->create();

    Review::factory()->pending()->create();
    $pendingHtml = $this->actingAs($admin)->get(route('admin.reviews'))->assertOk()->getContent();
    Review::query()->delete();

    Review::factory()->create(); // default status is Approved
    $approvedHtml = $this->actingAs($admin)->get(route('admin.reviews'))->assertOk()->getContent();
    Review::query()->delete();

    Review::factory()->rejected()->create();
    $rejectedHtml = $this->actingAs($admin)->get(route('admin.reviews'))->assertOk()->getContent();

    expect($pendingHtml)->toContain('-amber-');
    expect($approvedHtml)->toContain('-lime-');
    expect($rejectedHtml)->toContain('-red-');

    // The view must keep resolving the badge color dynamically per status,
    // not fall back to one static color for every review as it did before.
    expect(file_get_contents(resource_path('views/pages/admin/reviews.blade.php')))
        ->toContain('$reviewBadgeColor')
        ->not->toContain('color="orange"');
});

test('the reviews list is paginated', function () {
    $admin = User::factory()->admin()->create();

    collect(range(1, 16))->each(fn (int $i) => Review::factory()->create(['title' => sprintf('Review %02d', $i)]));

    $component = Volt::actingAs($admin)->test('admin.reviews');

    $component->assertSee('Review 16')->assertDontSee('Review 01');
    $component->call('nextPage')->assertSee('Review 01');
});
