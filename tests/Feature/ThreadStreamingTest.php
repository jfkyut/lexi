<?php

use App\Models\User;
use Illuminate\Http\Client\Request as ClientRequest;
use Illuminate\Support\Facades\Http;

test('authenticated users receive the assistant response as a stream', function () {
    Http::preventStrayRequests();

    config([
        'services.llm.url' => 'https://llm.test/v1/chat/completions',
        'services.llm.key' => 'test-key',
        'services.llm.model' => 'test-model',
    ]);

    Http::fake([
        'https://llm.test/v1/chat/completions' => Http::response(
            "data: {\"choices\":[{\"delta\":{\"content\":\"Hello\"}}]}\n\n".
            "data: {\"choices\":[{\"delta\":{\"content\":\" there\"}}]}\n\n".
            "data: [DONE]\n\n",
            200,
            ['Content-Type' => 'text/event-stream'],
        ),
    ]);

    $this->actingAs(User::factory()->create());

    $response = $this->post(route('thread.store'), [
        'message' => 'Hi',
    ]);

    $response->assertOk();
    expect($response->headers->get('Content-Type'))->toStartWith('text/event-stream');
    expect($response->streamedContent())
        ->toContain('Hello')
        ->toContain(' there')
        ->toContain('event: done');

    Http::assertSent(fn (ClientRequest $request): bool => $request->url() === 'https://llm.test/v1/chat/completions'
        && $request->data()['model'] === 'test-model'
        && $request->data()['stream'] === true
        && $request->data()['messages'][1] === ['role' => 'user', 'content' => 'Hi']);
});

test('message is required before starting the assistant stream', function () {
    Http::preventStrayRequests();

    $this->actingAs(User::factory()->create());

    $response = $this->postJson(route('thread.store'), [
        'message' => '',
    ]);

    $response->assertUnprocessable()
        ->assertJsonValidationErrors('message');
});

test('provider failures are sent as stream errors', function () {
    Http::preventStrayRequests();

    config([
        'services.llm.url' => 'https://llm.test/v1/chat/completions',
        'services.llm.key' => 'test-key',
        'services.llm.model' => 'test-model',
    ]);

    Http::fake([
        'https://llm.test/v1/chat/completions' => Http::response(['error' => 'Unavailable'], 503),
    ]);

    $this->actingAs(User::factory()->create());

    $response = $this->post(route('thread.store'), [
        'message' => 'Hi',
    ]);

    $response->assertOk();
    expect($response->streamedContent())
        ->toContain('event: error')
        ->toContain('The assistant could not complete this response.');
});
