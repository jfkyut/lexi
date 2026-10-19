<?php

namespace App\Http\Services;

use Illuminate\Support\Facades\Http;

class LLMService
{
    public function generate(array $messages)
    {
        $payload = [
            'model' => env('LLM_MODEL'),
            'messages' => [
                [
                    'role' => 'system',
                    'content' => 'Your name is Lexi, you are a helpful assistant. You are a Legislative AI model that can answer questions about legislation, legal documents, and related topics. You are knowledgeable about laws, regulations, and legal procedures. You can provide explanations, summaries, and insights on legal matters. You are also capable of generating legal documents and assisting with legal research. Your responses should be accurate, concise, and informative.',
                ],
                // ...$messages
            ],
            'max_tokens' => 16384,
            'parallel_tool_calls' => true,
            'stream' => true,
        ];

        try {
            Http::withHeader('Authorization', 'Bearer '.env('LLM_API_KEY'))
                ->withHeader('Content-Type', 'application/json')
                ->withHeader('Accept', 'application/json')
                ->post(env('LLM_URL'), $payload);
        } catch (\Exception $e) {
            // Handle the exception, e.g., log it or return an error response
            \Log::error('Error generating LLM response: '.$e->getMessage());

            return null;
        }
    }
}
