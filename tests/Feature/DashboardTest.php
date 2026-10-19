<?php

use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

test('guests are redirected to the login page', function () {
    $response = $this->get(route('dashboard'));
    $response->assertRedirect(route('login'));
});

test('authenticated users can visit the new chat page', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $response = $this->get(route('dashboard'));
    $response->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('NewChat'));
});

test('the new chat page shares only the users threads ordered by latest message', function () {
    $user = User::factory()->create();
    $otherUser = User::factory()->create();

    $firstThread = $user->threads()->create(['title' => 'First thread']);
    $firstMessage = $firstThread->messages()->create([
        'role' => 'user',
        'content' => 'First message',
    ]);

    $secondThread = $user->threads()->create(['title' => 'Second thread']);
    $secondThread->messages()->create([
        'role' => 'user',
        'content' => 'Second message',
    ]);

    $firstMessage->forceFill(['created_at' => now()->addMinute()])->save();
    $otherUser->threads()->create(['title' => 'Another users thread']);

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('threads', 2)
            ->where('threads.0.id', $firstThread->id)
            ->where('threads.0.title', 'First thread')
            ->where('threads.1.id', $secondThread->id)
            ->where('threads.1.title', 'Second thread'));
});
