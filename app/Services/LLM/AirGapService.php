<?php

namespace App\Services\LLM;

use App\Models\AppSetting;
use App\Services\Execution\Guards\NetworkGuard;

/**
 * Manages air-gap mode — restricts all LLM calls to local-only endpoints.
 */
class AirGapService
{
    public function __construct(
        protected NetworkGuard $networkGuard,
    ) {}

    /**
     * Check if air-gap mode is enabled.
     */
    public function isEnabled(): bool
    {
        return (bool) AppSetting::get('air_gap_mode', false);
    }

    /**
     * Enable air-gap mode.
     */
    public function enable(): void
    {
        AppSetting::set('air_gap_mode', true);
    }

    /**
     * Disable air-gap mode.
     */
    public function disable(): void
    {
        AppSetting::set('air_gap_mode', false);
    }

    /**
     * Get the current air-gap status with details.
     */
    public function status(): array
    {
        $enabled = $this->isEnabled();

        return [
            'enabled' => $enabled,
            'allowed_providers' => $enabled ? $this->getAllowedProviders() : ['all'],
            'blocked_providers' => $enabled ? $this->getBlockedProviders() : [],
            'allowed_hosts' => $this->getAllowedHosts(),
        ];
    }

    /**
     * Check if a model is allowed under current air-gap settings.
     */
    public function isModelAllowed(string $model): bool
    {
        if (! $this->isEnabled()) {
            return true;
        }

        $factory = new LLMProviderFactory();
        $providerName = $factory->providerName($model);

        return in_array($providerName, $this->getAllowedProviders());
    }

    /**
     * Validate that a URL is reachable under air-gap rules.
     */
    public function validateUrl(string $url): ?string
    {
        if (! $this->isEnabled()) {
            return null;
        }

        $this->networkGuard->configure([
            'air_gap_mode' => true,
            'allowed_hosts' => $this->getAllowedHosts(),
        ]);

        return $this->networkGuard->check($url);
    }

    /**
     * Get providers that work in air-gap mode.
     */
    protected function getAllowedProviders(): array
    {
        $providers = ['ollama'];

        // Custom endpoints on localhost are also allowed
        $customEndpoints = \App\Models\CustomEndpoint::where('enabled', true)->get();
        foreach ($customEndpoints as $ep) {
            $parsed = parse_url($ep->base_url);
            $host = $parsed['host'] ?? '';
            if (in_array($host, ['localhost', '127.0.0.1', '::1']) || str_ends_with($host, '.local')) {
                $providers[] = 'custom:' . $ep->slug;
            }
        }

        return $providers;
    }

    /**
     * Get providers blocked in air-gap mode.
     */
    protected function getBlockedProviders(): array
    {
        return ['anthropic', 'openai', 'gemini', 'grok'];
    }

    /**
     * Get explicitly allowed hosts.
     */
    protected function getAllowedHosts(): array
    {
        $hosts = AppSetting::get('air_gap_allowed_hosts');

        if (is_string($hosts)) {
            return array_filter(array_map('trim', explode(',', $hosts)));
        }

        return is_array($hosts) ? $hosts : [];
    }
}
