<?php

namespace App\Services\A2a;

/**
 * Value object representing an A2A agent card (/.well-known/agent.json).
 */
class AgentCard
{
    public function __construct(
        public readonly string $name,
        public readonly string $url,
        public readonly ?string $description = null,
        public readonly array $skills = [],
        public readonly ?string $version = null,
        public readonly ?string $provider = null,
        public readonly ?string $documentationUrl = null,
        public readonly array $capabilities = [],
        public readonly array $authentication = [],
        public readonly array $raw = [],
    ) {}

    /**
     * Parse an agent card from JSON response data.
     */
    public static function fromArray(array $data, string $baseUrl): self
    {
        return new self(
            name: $data['name'] ?? 'Unknown Agent',
            url: $baseUrl,
            description: $data['description'] ?? null,
            skills: self::parseSkills($data['skills'] ?? []),
            version: $data['version'] ?? null,
            provider: $data['provider']['organization'] ?? $data['provider'] ?? null,
            documentationUrl: $data['documentationUrl'] ?? null,
            capabilities: $data['capabilities'] ?? [],
            authentication: $data['authentication'] ?? [],
            raw: $data,
        );
    }

    /**
     * Parse skills array from agent card.
     */
    private static function parseSkills(array $skills): array
    {
        return array_map(fn ($skill) => [
            'id' => $skill['id'] ?? null,
            'name' => $skill['name'] ?? 'Unknown',
            'description' => $skill['description'] ?? null,
            'tags' => $skill['tags'] ?? [],
            'examples' => $skill['examples'] ?? [],
        ], $skills);
    }

    /**
     * Get skill names as a flat array.
     */
    public function skillNames(): array
    {
        return array_column($this->skills, 'name');
    }

    /**
     * Check if the agent supports a specific capability.
     */
    public function hasCapability(string $capability): bool
    {
        return in_array($capability, $this->capabilities, true);
    }
}
