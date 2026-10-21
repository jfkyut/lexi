<?php

namespace App\Http\Middleware;

use App\Models\Thread;
use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that's loaded on the first page visit.
     *
     * @see https://inertiajs.com/server-side-setup#root-template
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determines the current asset version.
     *
     * @see https://inertiajs.com/asset-versioning
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @see https://inertiajs.com/shared-data
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        return [
            ...parent::share($request),
            'name' => config('app.name'),
            'auth' => [
                'user' => $request->user(),
            ],
            'threads' => fn (): array => $request->user()
                ?->threads()
                ->withMax('messages', 'created_at')
                ->orderByDesc('threads.is_pinned')
                ->orderByRaw('COALESCE(messages_max_created_at, threads.created_at) DESC')
                ->orderByDesc('threads.id')
                ->get(['threads.id', 'threads.title', 'threads.is_pinned'])
                ->map(fn (Thread $thread): array => [
                    'id' => $thread->id,
                    'title' => $thread->title,
                    'is_pinned' => $thread->is_pinned,
                ])
                ->all() ?? [],
            'sidebarOpen' => ! $request->hasCookie('sidebar_state') || $request->cookie('sidebar_state') === 'true',
        ];
    }
}
