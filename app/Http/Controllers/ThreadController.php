<?php

namespace App\Http\Controllers;

use App\Http\Services\LLMService;
use App\Models\Message;
use App\Models\Thread;
use Generator;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\StreamedEvent;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;
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
    public function index(): void
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'message' => ['required', 'string', 'max:10000'],
        ]);

        $user = $request->user();
        abort_unless($user !== null, 401);

        [$thread, $message] = DB::transaction(function () use ($user, $validated): array {
            $thread = $user->threads()->create([
                'title' => 'New chat',
            ]);

            $message = $thread->messages()->create([
                'role' => 'user',
                'content' => $validated['message'],
            ]);

            return [$thread, $message];
        });

        return redirect()->route('threads.show', [
            'thread' => $thread,
            'pendingMessageId' => $message->id,
        ]);
    }

    /**
     * Display the specified resource.
     */
    public function show(Request $request, Thread $thread): InertiaResponse
    {
        $this->authorizeThread($request, $thread);

        $messages = $thread->messages()
            ->orderBy('id')
            ->get(['id', 'role', 'content'])
            ->filter(fn (Message $message): bool => $message->role === 'user' || $message->content !== null)
            ->values();

        $pendingMessageId = $request->integer('pendingMessageId');

        if ($pendingMessageId > 0) {
            $pendingMessageIndex = $messages->search(
                fn (Message $message): bool => $message->id === $pendingMessageId && $message->role === 'user'
            );

            if ($pendingMessageIndex === false) {
                $pendingMessageId = null;
            } else {
                $nextMessage = $messages->get($pendingMessageIndex + 1);

                if ($nextMessage instanceof Message && $nextMessage->role === 'assistant' && $nextMessage->content !== null) {
                    $pendingMessageId = null;
                }
            }
        } else {
            $pendingMessageId = null;
        }

        return Inertia::render('chat/Chat', [
            'thread' => [
                'id' => $thread->id,
                'title' => $thread->title,
            ],
            'messages' => $messages,
            'pendingMessageId' => $pendingMessageId,
        ]);
    }

    public function storeMessage(Request $request, Thread $thread): StreamedResponse
    {
        $this->authorizeThread($request, $thread);

        $validated = $request->validate([
            'message' => ['required_without:message_id', 'nullable', 'string', 'max:10000'],
            'message_id' => ['nullable', 'integer', 'min:1'],
        ]);

        if (isset($validated['message_id'])) {
            $userMessage = $thread->messages()
                ->where('role', 'user')
                ->findOrFail((int) $validated['message_id']);
        } else {
            $userMessage = $thread->messages()->create([
                'role' => 'user',
                'content' => $validated['message'],
            ]);
        }

        $nextMessage = $thread->messages()
            ->where('id', '>', $userMessage->id)
            ->orderBy('id')
            ->first();

        if ($nextMessage instanceof Message && $nextMessage->role === 'assistant' && $nextMessage->content !== null) {
            $assistantMessage = $nextMessage;
            $existingContent = $assistantMessage->content;
        } else {
            $assistantMessage = $nextMessage instanceof Message && $nextMessage->role === 'assistant'
                ? $nextMessage
                : $thread->messages()->create([
                    'role' => 'assistant',
                    'content' => null,
                ]);
            $existingContent = null;
        }

        $historyMessages = $thread->messages()
            ->where('id', '<=', $userMessage->id)
            ->whereIn('role', ['user', 'assistant'])
            ->whereNotNull('content')
            ->orderBy('id')
            ->get(['role', 'content']);

        $history = [];

        foreach ($historyMessages as $historyMessage) {
            if (is_string($historyMessage->role) && is_string($historyMessage->content)) {
                $history[] = [
                    'role' => $historyMessage->role,
                    'content' => $historyMessage->content,
                ];
            }
        }

        $shouldSuggestTitle = $thread->title === 'New chat';

        return response()->eventStream(function () use ($history, $assistantMessage, $existingContent, $shouldSuggestTitle, $thread): Generator {
            $responseContent = $existingContent ?? '';

            if ($existingContent !== null) {
                yield ['content' => $existingContent];
            } else {
                try {
                    foreach ($this->llmService->stream($history) as $content) {
                        $responseContent .= $content;
                        yield ['content' => $content];
                    }

                    $assistantMessage->update(['content' => $responseContent]);
                } catch (Throwable $exception) {
                    $assistantMessage->delete();
                    report($exception);

                    yield new StreamedEvent('error', [
                        'message' => 'The assistant could not complete this response.',
                    ]);

                    return;
                }
            }

            if ($shouldSuggestTitle && isset($history[0]['content'])) {
                try {
                    $title = $this->llmService->suggestTitle($history[0]['content']);
                } catch (Throwable $exception) {
                    report($exception);
                    $title = Str::limit(Str::squish($history[0]['content']), 255, '');
                }

                $thread->update(['title' => $title]);
                yield new StreamedEvent('title', ['title' => $title]);
            }
        }, [
            'X-Accel-Buffering' => 'no',
        ], new StreamedEvent('done', '{}'));
    }

    private function authorizeThread(Request $request, Thread $thread): void
    {
        abort_unless($request->user()?->id === $thread->user_id, 404);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Thread $thread): void
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Thread $thread): void
    {
        //
    }
}
