<?php

namespace App\Services\A2a;

/**
 * Value object representing the result of an A2A task delegation.
 */
class A2aTaskResult
{
    public function __construct(
        public readonly string $taskId,
        public readonly string $status,
        public readonly ?string $output = null,
        public readonly array $artifacts = [],
        public readonly ?string $error = null,
        public readonly array $raw = [],
    ) {}

    public static function fromResponse(array $data): self
    {
        $result = $data['result'] ?? $data;

        return new self(
            taskId: $result['id'] ?? $result['taskId'] ?? '',
            status: $result['status']['state'] ?? $result['status'] ?? 'unknown',
            output: self::extractOutput($result),
            artifacts: $result['artifacts'] ?? [],
            error: $result['error']['message'] ?? null,
            raw: $data,
        );
    }

    public static function error(string $message): self
    {
        return new self(
            taskId: '',
            status: 'failed',
            error: $message,
        );
    }

    public function isCompleted(): bool
    {
        return $this->status === 'completed';
    }

    public function isFailed(): bool
    {
        return $this->status === 'failed';
    }

    public function isWorking(): bool
    {
        return in_array($this->status, ['working', 'submitted', 'input-required']);
    }

    /**
     * Get text content from the result.
     */
    public function text(): string
    {
        if ($this->output !== null) {
            return $this->output;
        }

        if ($this->error !== null) {
            return "Error: {$this->error}";
        }

        // Extract text from artifacts
        $texts = [];
        foreach ($this->artifacts as $artifact) {
            foreach ($artifact['parts'] ?? [] as $part) {
                if (($part['type'] ?? '') === 'text') {
                    $texts[] = $part['text'] ?? '';
                }
            }
        }

        return implode("\n", $texts) ?: 'No output';
    }

    private static function extractOutput(array $result): ?string
    {
        // Try message.parts first (A2A spec)
        $parts = $result['status']['message']['parts'] ?? [];
        $texts = [];
        foreach ($parts as $part) {
            if (($part['type'] ?? '') === 'text') {
                $texts[] = $part['text'] ?? '';
            }
        }
        if (! empty($texts)) {
            return implode("\n", $texts);
        }

        // Try artifacts
        foreach ($result['artifacts'] ?? [] as $artifact) {
            foreach ($artifact['parts'] ?? [] as $part) {
                if (($part['type'] ?? '') === 'text') {
                    return $part['text'] ?? null;
                }
            }
        }

        // Try direct output field
        return $result['output'] ?? null;
    }
}
