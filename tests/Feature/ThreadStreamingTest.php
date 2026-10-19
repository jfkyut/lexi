<?php

use App\Models\User;
use Illuminate\Http\Client\Request as ClientRequest;
use Illuminate\Support\Facades\Http;
use Inertia\Testing\AssertableInertia as Assert;

test('creating a chat stores its first message and streams the first reply in the new thread', function () {
    config(['inertia.ssr.enabled' => false]);

    Http::preventStrayRequests();

    config([
        'services.llm.url' => 'https://llm.test/v1/chat/completions',
        'services.llm.key' => 'test-key',
        'services.llm.model' => 'test-model',
    ]);

    Http::fake(function (ClientRequest $request) {
        $content = $request->data()['messages'][0]['content'] === 'Suggest a concise, descriptive title for this chat in 2 to 6 words. Return only the title, with no quotation marks or explanation.'
            ? ['Legislative Bill Overview']
            : ['Hello', ' there'];

        $events = '';

        foreach ($content as $chunk) {
            $events .= 'data: '.json_encode(['choices' => [['delta' => ['content' => $chunk]]]])."\n\n";
        }

        return Http::response(
            $events."data: [DONE]\n\n",
            200,
            ['Content-Type' => 'text/event-stream'],
        );
    });

    $user = User::factory()->create();
    $this->actingAs($user);

    $createResponse = $this->post(route('threads.store'), [
        'message' => 'Hi Lexi',
    ]);
    $thread = $user->threads()->firstOrFail();
    $firstMessage = $thread->messages()->firstOrFail();

    $createResponse->assertRedirect(route('threads.show', [
        'thread' => $thread,
        'pendingMessageId' => $firstMessage->id,
    ]));
    $this->assertModelExists($thread);
    $this->assertModelExists($firstMessage);
    expect($thread->title)->toBe('New chat');

    $this->get(route('threads.show', [
        'thread' => $thread,
        'pendingMessageId' => $firstMessage->id,
    ]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('chat/Chat')
            ->where('pendingMessageId', $firstMessage->id)
            ->where('messages.0.content', 'Hi Lexi'));

    $streamResponse = $this->post(route('threads.messages.store', $thread), [
        'message_id' => $firstMessage->id,
    ]);

    $streamResponse->assertOk();
    expect($streamResponse->headers->get('Content-Type'))->toStartWith('text/event-stream');
    expect($streamResponse->streamedContent())
        ->toContain('Hello')
        ->toContain(' there')
        ->toContain('"title":"Legislative Bill Overview"')
        ->toContain("event: done\ndata: {}\n\n");

    expect($thread->fresh()->title)->toBe('Legislative Bill Overview');
    $this->assertDatabaseHas('messages', [
        'thread_id' => $thread->id,
        'role' => 'assistant',
        'content' => 'Hello there',
    ]);

    Http::assertSent(fn (ClientRequest $request): bool => $request->url() === 'https://llm.test/v1/chat/completions'
        && $request->data()['model'] === 'test-model'
        && $request->data()['stream'] === true
        && $request->data()['messages'][1] === ['role' => 'user', 'content' => 'Hi Lexi']);
    Http::assertSent(fn (ClientRequest $request): bool => $request->url() === 'https://llm.test/v1/chat/completions'
        && $request->data()['stream'] === true
        && $request->data()['messages'][0]['content'] === 'Suggest a concise, descriptive title for this chat in 2 to 6 words. Return only the title, with no quotation marks or explanation.'
        && $request->data()['messages'][1] === ['role' => 'user', 'content' => 'Hi Lexi']);
});

test('a title generation failure falls back to the first message without interrupting the response', function () {
    Http::preventStrayRequests();

    config([
        'services.llm.url' => 'https://llm.test/v1/chat/completions',
        'services.llm.key' => 'test-key',
        'services.llm.model' => 'test-model',
    ]);

    Http::fake(function (ClientRequest $request) {
        if ($request->data()['messages'][0]['content'] === 'Suggest a concise, descriptive title for this chat in 2 to 6 words. Return only the title, with no quotation marks or explanation.') {
            return Http::response(['error' => 'Unavailable'], 503);
        }

        return Http::response(
            "data: {\"choices\":[{\"delta\":{\"content\":\"Hello\"}}]}\n\n".
            "data: [DONE]\n\n",
            200,
            ['Content-Type' => 'text/event-stream'],
        );
    });

    $user = User::factory()->create();
    $thread = $user->threads()->create(['title' => 'New chat']);
    $thread->messages()->create(['role' => 'user', 'content' => 'Hi Lexi']);

    $response = $this->actingAs($user)->post(route('threads.messages.store', $thread), [
        'message_id' => $thread->messages()->firstOrFail()->id,
    ]);

    $response->assertOk();
    expect($response->streamedContent())
        ->toContain('Hello')
        ->toContain('"title":"Hi Lexi"')
        ->toContain("event: done\ndata: {}\n\n")
        ->not->toContain('event: error');
    expect($thread->fresh()->title)->toBe('Hi Lexi');
    $this->assertDatabaseHas('messages', [
        'thread_id' => $thread->id,
        'role' => 'assistant',
        'content' => 'Hello',
    ]);
});

test('a chat still using the default title is renamed from its first message on a later reply', function () {
    Http::preventStrayRequests();

    config([
        'services.llm.url' => 'https://llm.test/v1/chat/completions',
        'services.llm.key' => 'test-key',
        'services.llm.model' => 'test-model',
    ]);

    Http::fake(function (ClientRequest $request) {
        $isTitleRequest = $request->data()['messages'][0]['content'] === 'Suggest a concise, descriptive title for this chat in 2 to 6 words. Return only the title, with no quotation marks or explanation.';
        $content = $isTitleRequest ? 'First Question Summary' : 'Second reply';

        return Http::response(
            'data: '.json_encode(['choices' => [['delta' => ['content' => $content]]]])."\n\n".
            "data: [DONE]\n\n",
            200,
            ['Content-Type' => 'text/event-stream'],
        );
    });

    $user = User::factory()->create();
    $thread = $user->threads()->create(['title' => 'New chat']);
    $firstMessage = $thread->messages()->create([
        'role' => 'user',
        'content' => 'Explain the first question',
    ]);
    $thread->messages()->create(['role' => 'assistant', 'content' => 'First reply']);

    $response = $this->actingAs($user)->post(route('threads.messages.store', $thread), [
        'message' => 'A follow-up',
    ]);

    $response->assertOk();
    expect($response->streamedContent())->toContain('"title":"First Question Summary"');
    expect($thread->fresh()->title)->toBe('First Question Summary');

    Http::assertSent(fn (ClientRequest $request): bool => $request->data()['messages'][0]['content'] === 'Suggest a concise, descriptive title for this chat in 2 to 6 words. Return only the title, with no quotation marks or explanation.'
        && $request->data()['messages'][1] === ['role' => 'user', 'content' => $firstMessage->content]);
});

test('the chat page displays the saved conversation for its owner', function () {
    $user = User::factory()->create();
    $thread = $user->threads()->create(['title' => 'A question']);
    $message = $thread->messages()->create([
        'role' => 'user',
        'content' => 'What is a bill?',
    ]);

    $this->actingAs($user)
        ->get(route('threads.show', $thread))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('chat/Chat')
            ->where('thread.id', $thread->id)
            ->where('messages.0.id', $message->id)
            ->where('messages.0.content', 'What is a bill?')
            ->where('pendingMessageId', null));
});

test('users cannot view or send messages to another users thread', function () {
    $owner = User::factory()->create();
    $thread = $owner->threads()->create(['title' => 'Private thread']);
    $otherUser = User::factory()->create();

    $this->actingAs($otherUser)
        ->get(route('threads.show', $thread))
        ->assertNotFound();

    $this->post(route('threads.messages.store', $thread), [
        'message' => 'Read this thread',
    ])->assertNotFound();

    $this->assertDatabaseCount('messages', 0);
});

test('pending message identifiers are scoped to their thread', function () {
    $user = User::factory()->create();
    $sourceThread = $user->threads()->create(['title' => 'Source thread']);
    $sourceMessage = $sourceThread->messages()->create([
        'role' => 'user',
        'content' => 'Message from another thread',
    ]);
    $targetThread = $user->threads()->create(['title' => 'Target thread']);

    $this->actingAs($user)
        ->post(route('threads.messages.store', $targetThread), [
            'message_id' => $sourceMessage->id,
        ])
        ->assertNotFound();

    $this->assertDatabaseCount('messages', 1);
});

test('sending a message streams and saves the assistant reply with the previous conversation', function () {
    Http::preventStrayRequests();

    config([
        'services.llm.url' => 'https://llm.test/v1/chat/completions',
        'services.llm.key' => 'test-key',
        'services.llm.model' => 'test-model',
    ]);

    Http::fake([
        'https://llm.test/v1/chat/completions' => Http::response(
            "data: {\"choices\":[{\"delta\":{\"content\":\"Second answer\"}}]}\n\n".
            "data: [DONE]\n\n",
            200,
            ['Content-Type' => 'text/event-stream'],
        ),
    ]);

    $user = User::factory()->create();
    $thread = $user->threads()->create(['title' => 'A question']);
    $thread->messages()->create(['role' => 'user', 'content' => 'First question']);
    $thread->messages()->create(['role' => 'assistant', 'content' => 'First answer']);

    $response = $this->actingAs($user)->post(route('threads.messages.store', $thread), [
        'message' => 'Second question',
    ]);

    $response->assertOk();
    expect($response->streamedContent())->toContain('Second answer');
    $this->assertDatabaseHas('messages', [
        'thread_id' => $thread->id,
        'role' => 'user',
        'content' => 'Second question',
    ]);
    $this->assertDatabaseHas('messages', [
        'thread_id' => $thread->id,
        'role' => 'assistant',
        'content' => 'Second answer',
    ]);

    Http::assertSent(fn (ClientRequest $request): bool => array_slice($request->data()['messages'], 1) === [
        ['role' => 'user', 'content' => 'First question'],
        ['role' => 'assistant', 'content' => 'First answer'],
        ['role' => 'user', 'content' => 'Second question'],
    ]);
});

test('invalid messages are rejected before creating a thread', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->from(route('dashboard'))
        ->post(route('threads.store'), ['message' => ''])
        ->assertRedirect(route('dashboard'))
        ->assertSessionHasErrors('message');

    $this->assertDatabaseCount('threads', 0);
    $this->assertDatabaseCount('messages', 0);
});

test('provider failures are reported in the stream and do not save an empty assistant message', function () {
    Http::preventStrayRequests();

    config([
        'services.llm.url' => 'https://llm.test/v1/chat/completions',
        'services.llm.key' => 'test-key',
        'services.llm.model' => 'test-model',
    ]);

    Http::fake([
        'https://llm.test/v1/chat/completions' => Http::response(['error' => 'Unavailable'], 503),
    ]);

    $user = User::factory()->create();
    $thread = $user->threads()->create(['title' => 'A question']);

    $response = $this->actingAs($user)->post(route('threads.messages.store', $thread), [
        'message' => 'Hi',
    ]);

    $response->assertOk();
    expect($response->streamedContent())
        ->toContain('event: error')
        ->toContain('The assistant could not complete this response.');

    $this->assertDatabaseHas('messages', [
        'thread_id' => $thread->id,
        'role' => 'user',
        'content' => 'Hi',
    ]);
    $this->assertDatabaseMissing('messages', [
        'thread_id' => $thread->id,
        'role' => 'assistant',
    ]);
});
