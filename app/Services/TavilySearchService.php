<?php

namespace App\Services;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use RuntimeException;

class TavilySearchService
{
    /**
     * @return array<int, array{title: string, url: string, content: string}>
     */
    public function search(string $query): array
    {
        $query = trim($query);
        $key = config('services.tavily.key');

        if ($query === '' || mb_strlen($query) > 500) {
            throw new RuntimeException('The web search query is invalid.');
        }

        if (! is_string($key) || $key === '') {
            throw new RuntimeException('The Tavily web search service is not configured.');
        }

        try {
            $response = Http::connectTimeout(5)
                ->timeout(20)
                ->acceptJson()
                ->post(config('services.tavily.url'), [
                    'api_key' => $key,
                    'query' => $query,
                    'search_depth' => 'basic',
                    'max_results' => 5,
                    'include_answer' => false,
                    'include_raw_content' => false,
                ]);
        } catch (ConnectionException) {
            throw new RuntimeException('The Tavily web search service could not be reached.');
        }

        if (! $response->successful()) {
            throw new RuntimeException('Tavily web search returned HTTP status '.$response->status().'.');
        }

        $results = $response->json('results');

        if (! is_array($results)) {
            throw new RuntimeException('Tavily returned an invalid search response.');
        }

        $sources = [];

        foreach ($results as $result) {
            if (
                ! is_array($result)
                || ! is_string($result['title'] ?? null)
                || ! is_string($result['url'] ?? null)
                || ! is_string($result['content'] ?? null)
            ) {
                throw new RuntimeException('Tavily returned an invalid search result.');
            }

            $url = trim($result['url']);
            $scheme = parse_url($url, PHP_URL_SCHEME);

            if (filter_var($url, FILTER_VALIDATE_URL) === false || ! in_array(strtolower((string) $scheme), ['http', 'https'], true)) {
                throw new RuntimeException('Tavily returned an invalid source URL.');
            }

            $sources[] = [
                'title' => Str::limit(trim($result['title']) ?: $url, 300, ''),
                'url' => $url,
                'content' => Str::limit(trim($result['content']), 1500, ''),
            ];

            if (count($sources) === 5) {
                break;
            }
        }

        return $sources;
    }

    /**
     * @param  array<int, array{title: string, url: string, content: string}>  $sources
     */
    public function formatSources(array $sources): string
    {
        if ($sources === []) {
            return '';
        }

        $links = array_map(function (array $source): string {
            $title = preg_replace('/[\[\]\r\n]+/u', '', $source['title']) ?: 'Source';
            $url = str_replace(['(', ')'], ['%28', '%29'], $source['url']);

            return '- ['.$title.']('.$url.')';
        }, $sources);

        return "\n\n### Sources\n\n".implode("\n", $links);
    }
}
