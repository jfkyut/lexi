<?php

namespace App\Http\Services;

use RuntimeException;

class LLMToolCall
{
    /**
     * @param  array<string, mixed>  $arguments
     */
    public function __construct(
        public readonly string $id,
        public readonly string $name,
        public readonly string $argumentsJson,
        public readonly array $arguments,
    ) {}

    /**
     * @return array{id: string, type: string, function: array{name: string, arguments: string}}
     */
    public function requestData(): array
    {
        return [
            'id' => $this->id,
            'type' => 'function',
            'function' => [
                'name' => $this->name,
                'arguments' => $this->argumentsJson,
            ],
        ];
    }

    public static function fromStreamedCall(string $id, string $name, string $argumentsJson): self
    {
        $arguments = json_decode($argumentsJson, true, 512, JSON_THROW_ON_ERROR);

        if (! is_array($arguments) || array_is_list($arguments)) {
            throw new RuntimeException('The LLM returned invalid tool arguments.');
        }

        return new self($id, $name, $argumentsJson, $arguments);
    }
}
