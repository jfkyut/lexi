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

    Schema::connection('legacy')->create('document_statuses', function (Blueprint $table): void {
        $table->id();
        $table->string('name');
    });

    Schema::connection('legacy')->create('document_types', function (Blueprint $table): void {
        $table->id();
        $table->string('name');
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
        ->toContain('"reference_no": "ORD-2024-18"')
        ->toContain('"proposal_reference_no": "BILL-42"')
        ->toContain('"final_legislation_number": "ORD-2024-18"')
        ->toContain('"proposal_number": "BILL-42"')
        ->toContain('always show both and label them exactly as reference_no (final legislation number) and proposal_reference_no (proposal number)')
        ->toContain('These are different identifiers: never treat them as interchangeable.')
        ->toContain('metadata, not the full text');
});

test('legislation context selects approved records for ordinance requests', function () {
    DB::connection('legacy')->table('document_statuses')->insert([
        ['id' => 1, 'name' => 'proposal'],
        ['id' => 3, 'name' => 'approved'],
    ]);

    DB::connection('legacy')->table('documents')->insert([
        [
            'id' => 1,
            'title' => 'Tax ordinance for water quality',
            'status_id' => 1,
            'reference_no' => null,
            'proposal_reference_no' => 'BILL-2025-4',
        ],
        [
            'id' => 2,
            'title' => 'Tax ordinance for water quality',
            'status_id' => 3,
            'reference_no' => 'TAX-2025-4',
            'proposal_reference_no' => 'BILL-2025-4',
        ],
    ]);

    $context = app(LegislationContextService::class)->contextForQuestion('Show the tax ordinance for water quality');

    expect($context)
        ->toContain('"id": 2')
        ->toContain('"legislation_status": "approved"')
        ->not->toContain('"id": 1');
});

test('legislation context finds records by document type and includes the type name', function () {
    DB::connection('legacy')->table('document_types')->insert([
        ['id' => 3, 'name' => 'appropriation_ordinance'],
        ['id' => 4, 'name' => 'tax_ordinance'],
    ]);

    DB::connection('legacy')->table('documents')->insert([
        [
            'id' => 1,
            'title' => 'Annual budget adjustments',
            'type_id' => 3,
            'reference_no' => 'APP-2024-1',
        ],
        [
            'id' => 2,
            'title' => 'Annual budget adjustments',
            'type_id' => 4,
            'reference_no' => 'TAX-2024-1',
        ],
    ]);

    $context = app(LegislationContextService::class)->contextForQuestion('tax');

    expect($context)
        ->toContain('TAX-2024-1')
        ->toContain('"document_type": "tax_ordinance"')
        ->not->toContain('APP-2024-1');
});

test('legislation context orders approved matches by year and numeric sequence before references', function () {
    DB::connection('legacy')->table('document_statuses')->insert([
        ['id' => 3, 'name' => 'approved'],
        ['id' => 1, 'name' => 'proposal'],
    ]);

    DB::connection('legacy')->table('documents')->insert([
        [
            'id' => 1,
            'title' => 'Water ordinance',
            'status_id' => 3,
            'year' => '2024',
            'sequence_no' => '9',
            'reference_no' => 'ORD-2024-9',
            'proposal_reference_no' => 'BILL-2024-9',
            'created_at' => null,
        ],
        [
            'id' => 2,
            'title' => 'Water ordinance',
            'status_id' => 3,
            'year' => '2025',
            'sequence_no' => '2',
            'reference_no' => 'ORD-2025-2',
            'proposal_reference_no' => 'BILL-2025-2',
            'created_at' => null,
        ],
        [
            'id' => 3,
            'title' => 'Water ordinance',
            'status_id' => 3,
            'year' => '2025',
            'sequence_no' => '10',
            'reference_no' => 'ORD-2025-10',
            'proposal_reference_no' => 'BILL-A',
            'created_at' => null,
        ],
        [
            'id' => 4,
            'title' => 'Water ordinance',
            'status_id' => 3,
            'year' => '2025',
            'sequence_no' => '10',
            'reference_no' => 'ORD-2025-11',
            'proposal_reference_no' => 'BILL-A',
            'created_at' => null,
        ],
        [
            'id' => 5,
            'title' => 'Water ordinance',
            'status_id' => 3,
            'year' => '2025',
            'sequence_no' => '10',
            'reference_no' => 'ORD-2025-11',
            'proposal_reference_no' => 'BILL-Z',
            'created_at' => '2025-12-30 00:00:00',
        ],
        [
            'id' => 6,
            'title' => 'Water ordinance',
            'status_id' => 3,
            'year' => '2025',
            'sequence_no' => '10',
            'reference_no' => 'ORD-2025-11',
            'proposal_reference_no' => 'BILL-Z',
            'created_at' => '2025-12-31 00:00:00',
        ],
    ]);

    $context = app(LegislationContextService::class)->contextForQuestion('latest water ordinance');

    expect(strpos($context, '"id": 6'))->toBeLessThan(strpos($context, '"id": 5'))
        ->and(strpos($context, '"id": 5'))->toBeLessThan(strpos($context, '"id": 4'))
        ->and(strpos($context, '"id": 4'))->toBeLessThan(strpos($context, '"id": 3'))
        ->and(strpos($context, '"id": 3'))->toBeLessThan(strpos($context, '"id": 2'))
        ->and(strpos($context, '"id": 2'))->toBeLessThan(strpos($context, '"id": 1'));
});

test('legislation context orders latest proposed matches by year and numeric sequence', function () {
    DB::connection('legacy')->table('document_statuses')->insert([
        ['id' => 3, 'name' => 'approved'],
        ['id' => 1, 'name' => 'proposal'],
    ]);

    DB::connection('legacy')->table('documents')->insert([
        [
            'id' => 1,
            'title' => 'Water ordinance',
            'status_id' => 1,
            'proposal_month' => '2025-01',
            'year' => '2024',
            'sequence_no' => null,
            'reference_no' => null,
            'proposal_sequence_no' => '9',
            'proposal_reference_no' => 'BILL-2025-01-9',
        ],
        [
            'id' => 2,
            'title' => 'Water ordinance',
            'status_id' => 1,
            'proposal_month' => '2025-01',
            'year' => '2025',
            'sequence_no' => null,
            'reference_no' => null,
            'proposal_sequence_no' => '2',
            'proposal_reference_no' => 'BILL-2025-02-2',
        ],
        [
            'id' => 3,
            'title' => 'Water ordinance',
            'status_id' => 1,
            'proposal_month' => '2025-01',
            'year' => '2025',
            'sequence_no' => null,
            'reference_no' => null,
            'proposal_sequence_no' => '10',
            'proposal_reference_no' => 'BILL-2025-02-10',
        ],
        [
            'id' => 4,
            'title' => 'Water ordinance',
            'status_id' => 3,
            'year' => '2026',
            'proposal_month' => null,
            'sequence_no' => '20',
            'proposal_sequence_no' => null,
            'reference_no' => 'ORD-2026-20',
            'proposal_reference_no' => null,
        ],
    ]);

    $context = app(LegislationContextService::class)->contextForQuestion('latest proposed water ordinances not yet approved');

    expect($context)
        ->toContain('"legislation_status": "proposal"')
        ->not->toContain('"id": 4');

    expect(strpos($context, '"id": 3'))->toBeLessThan(strpos($context, '"id": 2'))
        ->and(strpos($context, '"id": 2'))->toBeLessThan(strpos($context, '"id": 1'));
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

    DB::connection('legacy')->table('document_types')->insert([
        'id' => 4,
        'name' => 'tax_ordinance',
    ]);

    DB::connection('legacy')->table('documents')->insert([
        'reference_no' => 'ORD-2024-18',
        'proposal_reference_no' => 'BILL-42',
        'title' => 'Riverside water protection ordinance',
        'proposal_title' => 'Riverside water protection',
        'type_id' => 4,
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
        && str_contains($request->data()['messages'][0]['content'], 'These are different identifiers: never treat them as interchangeable.')
        && str_contains($request->data()['messages'][0]['content'], '"final_legislation_number": "ORD-2024-18"')
        && str_contains($request->data()['messages'][0]['content'], '"proposal_number": "BILL-42"')
        && str_contains($request->data()['messages'][0]['content'], '"document_type": "tax_ordinance"')
        && $request->data()['messages'][1] === ['role' => 'user', 'content' => 'Tell me about BILL-42']);
});

test('the llm can search additional legislation records and use the results in its reply', function () {
    Http::preventStrayRequests();

    config([
        'services.llm.url' => 'https://llm.test/v1/chat/completions',
        'services.llm.key' => 'test-key',
        'services.llm.model' => 'test-model',
    ]);

    Http::fake(function (ClientRequest $request) {
        $messages = $request->data()['messages'];

        if (end($messages)['role'] === 'tool') {
            return Http::response(
                "data: {\"choices\":[{\"delta\":{\"content\":\"I found the Tricycle Franchise Regulation.\"}}]}\n\n".
                "data: [DONE]\n\n",
                200,
                ['Content-Type' => 'text/event-stream'],
            );
        }

        $arguments = json_encode(['query' => 'tricycle franchise'], JSON_THROW_ON_ERROR);

        return Http::response(
            'data: '.json_encode([
                'choices' => [[
                    'delta' => [
                        'tool_calls' => [[
                            'index' => 0,
                            'id' => 'call_legislation_search',
                            'type' => 'function',
                            'function' => [
                                'name' => 'search_legislation',
                                'arguments' => $arguments,
                            ],
                        ]],
                    ],
                ]],
            ], JSON_THROW_ON_ERROR)."\n\n".
            "data: [DONE]\n\n",
            200,
            ['Content-Type' => 'text/event-stream'],
        );
    });

    DB::connection('legacy')->table('documents')->insert([
        'reference_no' => 'ORD-2025-12',
        'title' => 'Tricycle Franchise Regulation',
        'remarks' => 'Sets local franchise requirements.',
    ]);

    $user = User::factory()->create();
    $thread = $user->threads()->create(['title' => 'Franchise question']);
    $response = $this->actingAs($user)->post(route('threads.messages.store', $thread), [
        'message' => 'Can you find the tricycle franchise regulation?',
    ]);

    $response->assertOk();
    expect($response->streamedContent())
        ->toContain('I found the Tricycle Franchise Regulation.')
        ->not->toContain('event: error');

    Http::assertSent(fn (ClientRequest $request): bool => isset($request->data()['tools'][1]['function'])
        && $request->data()['tools'][1]['function']['name'] === 'search_legislation'
        && str_contains($request->data()['messages'][0]['content'], 'Never claim you cannot access or search the connected records'));

    Http::assertSent(function (ClientRequest $request): bool {
        $messages = $request->data()['messages'];
        $toolMessage = end($messages);

        return $toolMessage['role'] === 'tool'
            && str_contains($toolMessage['content'], 'Tricycle Franchise Regulation')
            && str_contains($toolMessage['content'], 'ORD-2025-12');
    });
});
