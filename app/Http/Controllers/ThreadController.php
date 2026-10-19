<?php

namespace App\Http\Controllers;

use App\Http\Services\LLMService;
use App\Models\Thread;
use Illuminate\Http\Client\Request;

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
    public function store(Request $request)
    {
        $this->llmService->generate([
            'role' => 'user',
            'content' => $request->input('message'),
        ]);

        return back();
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
