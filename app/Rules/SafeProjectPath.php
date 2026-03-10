<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class SafeProjectPath implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $path = realpath($value) ?: $value;

        // Normalize: resolve ../ and ensure absolute
        if (! str_starts_with($path, '/')) {
            $fail('The project path must be an absolute path.');
            return;
        }

        // Block path traversal
        if (str_contains($path, '..')) {
            $fail('The project path must not contain path traversal (..).');
            return;
        }

        $allowedBases = self::getAllowedBases();

        foreach ($allowedBases as $base) {
            if (str_starts_with($path, $base . '/') || $path === $base) {
                return;
            }
        }

        $fail('The project path must be within: ' . implode(' or ', $allowedBases));
    }

    /**
     * Get the allowed base paths for the current OS.
     *
     * @return string[]
     */
    public static function getAllowedBases(): array
    {
        // Allow explicit override
        if ($custom = env('PROJECTS_ALLOWED_PATH')) {
            return [$custom];
        }

        // In Docker, use the host path that users type
        if ($hostPath = env('PROJECTS_HOST_PATH')) {
            return [$hostPath];
        }

        // Auto-detect based on OS and current user
        $user = env('USER') ?: (env('USERNAME') ?: (function_exists('posix_geteuid') ? (posix_getpwuid(posix_geteuid())['name'] ?? '') : ''));

        if (PHP_OS_FAMILY === 'Darwin') {
            return ["/Users/{$user}/Projects"];
        }

        return ["/home/{$user}/Projects"];
    }
}
