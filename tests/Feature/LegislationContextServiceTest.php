<?php

use App\Http\Services\LegislationContextService;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Client\Request as ClientRequest;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;

beforeEach(function () {
    config([
        'database.connections.legacy.driver' => 'sqlite',
        'database.connections.legacy.database' => ':memory:',
        'database.connections.legacy.prefix' => '',
    ]);

    DB::purge('legacy');

    Schema::connection('legacy')->create('documents', function (Blueprint $table): void {
        $table->id();
        $table->longText('title')->nullable();
        $table->unsignedBigInteger('council_id')->nullable();
        $table->string('classification')->nullable();
        $table->string('year')->nullable();
        $table->longText('reference_no')->nullable();
        $table->longText('date_adopted')->nullable();
        $table->longText('date_approved')->nullable();
        $table->string('proposal_reference_no')->nullable();
        $table->longText('proposal_title')->nullable();
        $table->longText('remarks')->nullable();
        $table->string('file_no')->nullable();
        $table->unsignedBigInteger('type_id')->nullable();
        $table->unsignedBigInteger('status_id')->nullable();
        $table->longText('other_author')->nullable();
        $table->longText('other_co_author')->nullable();
        $table->unsignedBigInteger('provincial_status_id')->nullable();
        $table->unsignedBigInteger('author_type_id')->nullable();
        $table->unsignedBigInteger('co_author_type_id')->nullable();
        $table->unsignedBigInteger('proposal_author_type_id')->nullable();
        $table->unsignedBigInteger('proposal_co_author_type_id')->nullable();
        $table->timestamp('created_at')->nullable();
        $table->timestamp('updated_at')->nullable();
        $table->unsignedBigInteger('sponsor_type_id')->nullable();
        $table->string('proposal_month')->nullable();
        $table->string('proposal_sequence_no')->nullable();
        $table->string('sequence_no')->nullable();
        $table->unsignedBigInteger('parent_id')->nullable();
        $table->unsignedBigInteger('incoming_id')->nullable();
        $table->string('records_room_status')->nullable();
    });

    Schema::connection('legacy')->create('authors', function (Blueprint $table): void {
        $table->string('id')->primary();
        $table->string('first_name')->nullable();
        $table->string('middle_name')->nullable();
        $table->string('last_name')->nullable();
        $table->string('suffix')->nullable();
    });

    Schema::connection('legacy')->create('committees', function (Blueprint $table): void {
        $table->id();
        $table->unsignedBigInteger('council_id')->nullable();
        $table->longText('committee')->nullable();
    });

    Schema::connection('legacy')->create('councils', function (Blueprint $table): void {
        $table->id();
        $table->string('number')->nullable();
        $table->date('start')->nullable();
        $table->date('end')->nullable();
    });

    foreach ([
        'document_authors',
        'document_co_authors',
        'document_proposal_authors',
        'document_proposal_co_authors',
        'document_sponsors',
    ] as $pivotTable) {
        Schema::connection('legacy')->create($pivotTable, function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('document_id');
            $table->string('author_id');
        });
    }

    Schema::connection('legacy')->create('document_committee_sponsors', function (Blueprint $table): void {
        $table->id();
        $table->unsignedBigInteger('document_id');
        $table->unsignedBigInteger('committee_id');
    });
});

test('legislation context includes matching reference numbers and titles', function () {
    DB::connection('legacy')->table('documents')->insert([
        'reference_no' => 'ORD-2024-18',
        'proposal_reference_no' => 'BILL-42',
        'title' => 'Riverside water protection ordinance',
        'proposal_title' => 'Riverside water protection',
    ]);

    $context = app(LegislationContextService::class)->contextForQuestion('Tell me about BILL-42');

    expect($context)
        ->toContain('BILL-42')
        ->toContain('ORD-2024-18')
        ->toContain('Riverside water protection ordinance')
        ->toContain('metadata, not the full text');
});

test('legislation context explicitly says when there are no matching records', function () {
    $context = app(LegislationContextService::class)->contextForQuestion('What is completely unknown?');

    expect($context)->toContain('No matching legislation records were found.');
});

test('legislation context includes named authors sponsors committee sponsors and council', function () {
    DB::connection('legacy')->table('councils')->insert([
        'id' => 7,
        'number' => 'Council 12',
        'start' => '2024-01-01',
        'end' => '2027-01-01',
    ]);
    DB::connection('legacy')->table('committees')->insert([
        'id' => 9,
        'council_id' => 7,
        'committee' => 'Water Resources Committee',
    ]);
    DB::connection('legacy')->table('authors')->insert([
        'id' => 'author-1',
        'first_name' => 'Avery',
        'middle_name' => 'M.',
        'last_name' => 'Rivera',
        'suffix' => 'Jr.',
    ]);
    DB::connection('legacy')->table('documents')->insert([
        'id' => 42,
        'title' => 'Riverside water protection ordinance',
        'council_id' => 7,
        'classification' => 'Ordinance',
        'year' => '2024',
        'reference_no' => 'ORD-2024-18',
        'proposal_reference_no' => 'BILL-42',
        'proposal_title' => 'Riverside water protection',
        'remarks' => 'Protects the local river basin.',
    ]);

    foreach ([
        'document_authors',
        'document_co_authors',
        'document_proposal_authors',
        'document_proposal_co_authors',
        'document_sponsors',
    ] as $pivotTable) {
        DB::connection('legacy')->table($pivotTable)->insert([
            'document_id' => 42,
            'author_id' => 'author-1',
        ]);
    }

    DB::connection('legacy')->table('document_committee_sponsors')->insert([
        'document_id' => 42,
        'committee_id' => 9,
    ]);

    $context = app(LegislationContextService::class)->contextForQuestion('Which bills were sponsored by Rivera?');

    expect($context)
        ->toContain('Avery M. Rivera Jr.')
        ->toContain('Water Resources Committee')
        ->toContain('Council 12')
        ->toContain('"classification": "Ordinance"')
        ->toContain('Protects the local river basin.')
        ->toContain('"proposal_co_authors"');
});

test('matching legislation is added to the system context sent to the llm', function () {
    Http::preventStrayRequests();

    config([
        'services.llm.url' => 'https://llm.test/v1/chat/completions',
        'services.llm.key' => 'test-key',
        'services.llm.model' => 'test-model',
    ]);

    Http::fake([
        'https://llm.test/v1/chat/completions' => Http::response(
            "data: {\"choices\":[{\"delta\":{\"content\":\"This is the matching proposal.\"}}]}\n\n".
            "data: [DONE]\n\n",
            200,
            ['Content-Type' => 'text/event-stream'],
        ),
    ]);

    DB::connection('legacy')->table('documents')->insert([
        'reference_no' => 'ORD-2024-18',
        'proposal_reference_no' => 'BILL-42',
        'title' => 'Riverside water protection ordinance',
        'proposal_title' => 'Riverside water protection',
    ]);

    $user = User::factory()->create();
    $thread = $user->threads()->create(['title' => 'Legislation question']);

    $response = $this->actingAs($user)->post(route('threads.messages.store', $thread), [
        'message' => 'Tell me about BILL-42',
    ]);

    $response->assertOk();
    expect($response->streamedContent())->toContain('This is the matching proposal.');

    Http::assertSent(fn (ClientRequest $request): bool => $request->data()['messages'][0]['role'] === 'system'
        && str_contains($request->data()['messages'][0]['content'], 'BILL-42')
        && str_contains($request->data()['messages'][0]['content'], 'Riverside water protection')
        && $request->data()['messages'][1] === ['role' => 'user', 'content' => 'Tell me about BILL-42']);
});
