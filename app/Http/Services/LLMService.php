<?php

namespace App\Http\Services;

use Generator;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class LLMService
{
    /**
     * @param  array<int, array{role: string, content: string}>  $messages
     * @return Generator<int, string>
     */
    public function stream(array $messages): Generator
    {
        $url = config('services.llm.url');
        $key = config('services.llm.key');
        $model = config('services.llm.model');

        if (! is_string($url) || $url === '' || ! is_string($key) || $key === '' || ! is_string($model) || $model === '') {
            throw new RuntimeException('The LLM service is not configured.');
        }

        $payload = [
            'model' => $model,
            'messages' => [
                [
                    'role' => 'system',
                    'content' => 'Your name is Lexi, you are a helpful assistant. You are a Legislative AI model that can answer questions about legislation, legal documents, and related topics. You are knowledgeable about laws, regulations, and legal procedures. You can provide explanations, summaries, and insights on legal matters. You are also capable of generating legal documents and assisting with legal research. Your responses should be accurate, concise, and informative.',
                ],
                ...$messages,
            ],
            'max_tokens' => 16384,
            'parallel_tool_calls' => true,
            'stream' => true,
        ];

        $response = Http::withToken($key)
            ->accept('text/event-stream')
            ->connectTimeout(10)
            ->timeout(120)
            ->withOptions(['stream' => true])
            ->post($url, $payload)
            ->throw();

        $body = $response->toPsrResponse()->getBody();
        $buffer = '';
        $dataLines = [];

        $processLine = function (string $line) use (&$dataLines): ?string {
            if ($line === '') {
                if ($dataLines === []) {
                    return null;
                }

                $eventData = $dataLines;
                $dataLines = [];

                return $this->contentFromEvent($eventData);
            }

            if (str_starts_with($line, 'data:')) {
                $dataLines[] = ltrim(substr($line, 5), ' ');
            }

            return null;
        };

        while (! $body->eof()) {
            $buffer .= $body->read(1024);

            while (($lineEnd = strpos($buffer, "\n")) !== false) {
                $line = rtrim(substr($buffer, 0, $lineEnd), "\r");
                $buffer = substr($buffer, $lineEnd + 1);
                $content = $processLine($line);

                if ($content !== null && $content !== '') {
                    yield $content;
                }
            }
        }

        if ($buffer !== '') {
            $content = $processLine(rtrim($buffer, "\r"));

            if ($content !== null && $content !== '') {
                yield $content;
            }
        }

        $content = $processLine('');

        if ($content !== null && $content !== '') {
            yield $content;
        }
    }

    /**
     * @param  array<int, string>  $dataLines
     */
    private function contentFromEvent(array $dataLines): ?string
    {
        $data = implode("\n", $dataLines);

        if ($data === '[DONE]') {
            return null;
        }

        $event = json_decode($data, true, 512, JSON_THROW_ON_ERROR);

        if (isset($event['error'])) {
            throw new RuntimeException('The LLM provider returned an error.');
        }

        $content = data_get($event, 'choices.0.delta.content');

        return is_string($content) ? $content : null;
    }
}
