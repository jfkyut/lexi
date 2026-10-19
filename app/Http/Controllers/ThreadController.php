<?php

namespace App\Http\Controllers;

use App\Http\Services\LLMService;
use App\Models\Thread;
use Generator;
use Illuminate\Http\Request;
use Illuminate\Http\StreamedEvent;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

class ThreadController extends Controller
{
    public function __construct(
        protected LLMService $llmService,
    ) {}

    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request): StreamedResponse
    {
        $validated = $request->validate([
            'message' => ['required', 'string', 'max:10000'],
        ]);

        return response()->eventStream(function () use ($validated): Generator {
            try {
                foreach ($this->llmService->stream([
                    [
                        'role' => 'user',
                        'content' => $validated['message'],
                    ],
                ]) as $content) {
                    yield ['content' => $content];
                }
            } catch (Throwable $exception) {
                report($exception);

                yield new StreamedEvent('error', [
                    'message' => 'The assistant could not complete this response.',
                ]);
            }
        }, [
            'X-Accel-Buffering' => 'no',
        ], new StreamedEvent('done', []));
    }

    /**
     * Display the specified resource.
     */
    public function show(Thread $thread)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Thread $thread)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Thread $thread)
    {
        //
    }
}
