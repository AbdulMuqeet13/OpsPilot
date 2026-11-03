<?php

use App\Models\Ticket;
use App\Models\TicketCategory;
use App\Models\User;
use App\Models\Workspace;

use function Pest\Laravel\actingAs;

it('forbids agents from managing categories', function () {
    $agent = User::factory()->agent()->create();

    actingAs($agent)->get(route('ticket-categories.index'))->assertForbidden();
    actingAs($agent)->post(route('ticket-categories.store'), ['name' => 'Refunds', 'color' => 'blue'])->assertForbidden();
});

it('adds a category at the end of the list', function () {
    $admin = User::factory()->create();
    TicketCategory::factory()->for($admin->workspace)->create(['position' => 3]);

    actingAs($admin)
        ->post(route('ticket-categories.store'), ['name' => 'Refunds', 'description' => 'Customer wants money back', 'color' => 'rose'])
        ->assertSessionHasNoErrors();

    expect($admin->workspace->ticketCategories()->where('name', 'Refunds')->sole())
        ->position->toBe(4)
        ->color->value->toBe('rose');
});

it('rejects a duplicate name within the workspace but allows it in another', function () {
    $admin = User::factory()->create();
    TicketCategory::factory()->for($admin->workspace)->create(['name' => 'Refunds']);
    TicketCategory::factory()->for(Workspace::factory())->create(['name' => 'Delivery delays']);

    actingAs($admin)
        ->post(route('ticket-categories.store'), ['name' => 'Refunds', 'color' => 'blue'])
        ->assertSessionHasErrors(['name' => 'You already have a category with that name.']);

    actingAs($admin)
        ->post(route('ticket-categories.store'), ['name' => 'Delivery delays', 'color' => 'blue'])
        ->assertSessionHasNoErrors();
});

it('caps the number of categories', function () {
    $admin = User::factory()->create();
    TicketCategory::factory()->for($admin->workspace)->count(12)->sequence(fn ($sequence) => ['name' => "Category {$sequence->index}"])->create();

    actingAs($admin)
        ->post(route('ticket-categories.store'), ['name' => 'One too many', 'color' => 'blue'])
        ->assertSessionHasErrors('name');
});

it('keeps tickets but uncategorises them when a category is removed', function () {
    $admin = User::factory()->create();
    $category = TicketCategory::factory()->for($admin->workspace)->create();
    $ticket = Ticket::factory()->for($admin->workspace)->create(['category_id' => $category->id]);

    actingAs($admin)->delete(route('ticket-categories.destroy', $category))->assertRedirect();

    expect(TicketCategory::find($category->id))->toBeNull()
        ->and($ticket->fresh()->category_id)->toBeNull();
});

it('returns 404 for another workspace\'s category', function () {
    $foreign = TicketCategory::factory()->for(Workspace::factory())->create();

    actingAs(User::factory()->create())
        ->patch(route('ticket-categories.update', $foreign), ['name' => 'Hijacked', 'color' => 'blue'])
        ->assertNotFound();

    actingAs(User::factory()->create())->delete(route('ticket-categories.destroy', $foreign))->assertNotFound();
});
