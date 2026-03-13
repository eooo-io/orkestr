<?php

namespace App\Services\Execution\Guards;

class OutputGuard
{
    /**
     * Validate agent output for safety concerns.
     * Returns an array of warnings (empty if clean).
     */
    public function check(string $output): array
    {
        $warnings = [];

        // Check for PII patterns
        $piiWarning = $this->detectPii($output);
        if ($piiWarning) {
            $warnings[] = $piiWarning;
        }

        // Check for credential/secret patterns
        $secretWarning = $this->detectSecrets($output);
        if ($secretWarning) {
            $warnings[] = $secretWarning;
        }

        // Check output length
        if (strlen($output) > 500_000) {
            $warnings[] = 'Output exceeds maximum length (500KB)';
        }

        return $warnings;
    }

    /**
     * Redact detected PII from output.
     */
    public function redact(string $output): string
    {
        // Redact email addresses
        $output = preg_replace(
            '/[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}/',
            '[EMAIL_REDACTED]',
            $output
        );

        // Redact phone numbers (US format)
        $output = preg_replace(
            '/\b(\+?1[-.\s]?)?\(?\d{3}\)?[-.\s]?\d{3}[-.\s]?\d{4}\b/',
            '[PHONE_REDACTED]',
            $output
        );

        // Redact SSN patterns
        $output = preg_replace(
            '/\b\d{3}[-\s]?\d{2}[-\s]?\d{4}\b/',
            '[SSN_REDACTED]',
            $output
        );

        // Redact credit card numbers
        $output = preg_replace(
            '/\b\d{4}[-\s]?\d{4}[-\s]?\d{4}[-\s]?\d{4}\b/',
            '[CARD_REDACTED]',
            $output
        );

        return $output;
    }

    /**
     * Detect PII patterns.
     */
    private function detectPii(string $output): ?string
    {
        $patterns = [
            'SSN' => '/\b\d{3}-\d{2}-\d{4}\b/',
            'credit card' => '/\b\d{4}[-\s]?\d{4}[-\s]?\d{4}[-\s]?\d{4}\b/',
        ];

        $found = [];
        foreach ($patterns as $type => $pattern) {
            if (preg_match($pattern, $output)) {
                $found[] = $type;
            }
        }

        if (! empty($found)) {
            return 'Potential PII detected in output: ' . implode(', ', $found);
        }

        return null;
    }

    /**
     * Detect credential/secret patterns.
     */
    private function detectSecrets(string $output): ?string
    {
        $patterns = [
            'API key' => '/\b(sk-[a-zA-Z0-9]{20,}|AKIA[A-Z0-9]{16})\b/',
            'private key' => '/-----BEGIN (RSA |EC |DSA )?PRIVATE KEY-----/',
            'password assignment' => '/password\s*[:=]\s*["\'][^"\']+["\']/i',
            'connection string' => '/(?:mysql|postgres|mongodb):\/\/[^\s]+:[^\s]+@/i',
        ];

        $found = [];
        foreach ($patterns as $type => $pattern) {
            if (preg_match($pattern, $output)) {
                $found[] = $type;
            }
        }

        if (! empty($found)) {
            return 'Potential secrets detected in output: ' . implode(', ', $found);
        }

        return null;
    }
}
