<?php

namespace App\Services;

use App\Models\AppSetting;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class HealthCheckService
{
    /**
     * Run all health checks and return results.
     *
     * @return array<string, array{status: string, message: string, latency_ms: int|null}>
     */
    public function runAll(): array
    {
        return [
            'database' => $this->checkDatabase(),
            'cache' => $this->checkCache(),
            'queue' => $this->checkQueue(),
            'storage' => $this->checkStorage(),
            'anthropic' => $this->checkProvider('anthropic'),
            'openai' => $this->checkProvider('openai'),
            'ollama' => $this->checkOllama(),
        ];
    }

    public function checkDatabase(): array
    {
        try {
            $start = microtime(true);
            DB::select('SELECT 1');
            $latency = (int) ((microtime(true) - $start) * 1000);

            return ['status' => 'healthy', 'message' => 'Database connected', 'latency_ms' => $latency];
        } catch (\Exception $e) {
            return ['status' => 'unhealthy', 'message' => 'Database unreachable: '.$e->getMessage(), 'latency_ms' => null];
        }
    }

    public function checkCache(): array
    {
        try {
            $start = microtime(true);
            Cache::put('health_check', true, 10);
            $value = Cache::get('health_check');
            Cache::forget('health_check');
            $latency = (int) ((microtime(true) - $start) * 1000);

            return [
                'status' => $value ? 'healthy' : 'degraded',
                'message' => $value ? 'Cache working' : 'Cache write/read failed',
                'latency_ms' => $latency,
            ];
        } catch (\Exception $e) {
            return ['status' => 'unhealthy', 'message' => 'Cache error: '.$e->getMessage(), 'latency_ms' => null];
        }
    }

    public function checkQueue(): array
    {
        $driver = config('queue.default');

        return ['status' => 'healthy', 'message' => "Queue driver: {$driver}", 'latency_ms' => null];
    }

    public function checkStorage(): array
    {
        try {
            $testFile = 'health_check_'.Str::random(8).'.tmp';
            Storage::disk('local')->put($testFile, 'ok');
            $content = Storage::disk('local')->get($testFile);
            Storage::disk('local')->delete($testFile);

            return [
                'status' => $content === 'ok' ? 'healthy' : 'degraded',
                'message' => 'Storage read/write working',
                'latency_ms' => null,
            ];
        } catch (\Exception $e) {
            return ['status' => 'unhealthy', 'message' => 'Storage error: '.$e->getMessage(), 'latency_ms' => null];
        }
    }

    public function checkProvider(string $provider): array
    {
        $keyMap = [
            'anthropic' => 'anthropic.api_key',
            'openai' => 'services.openai.api_key',
        ];

        $configKey = $keyMap[$provider] ?? null;
        if (! $configKey) {
            return ['status' => 'unknown', 'message' => 'Unknown provider', 'latency_ms' => null];
        }

        // Check from app_settings first, then config
        $key = AppSetting::get("{$provider}_api_key") ?: config($configKey);

        if (empty($key)) {
            return ['status' => 'not_configured', 'message' => 'API key not set', 'latency_ms' => null];
        }

        return ['status' => 'configured', 'message' => 'API key present (not validated)', 'latency_ms' => null];
    }

    public function checkOllama(): array
    {
        $url = AppSetting::get('ollama_url') ?: config('services.ollama.url', 'http://localhost:11434');

        if (empty($url)) {
            return ['status' => 'not_configured', 'message' => 'Ollama URL not set', 'latency_ms' => null];
        }

        try {
            $start = microtime(true);
            $response = Http::timeout(5)->get("{$url}/api/version");
            $latency = (int) ((microtime(true) - $start) * 1000);

            if ($response->successful()) {
                $version = $response->json('version', 'unknown');

                return ['status' => 'healthy', 'message' => "Ollama v{$version}", 'latency_ms' => $latency];
            }

            return ['status' => 'degraded', 'message' => 'Ollama responded with '.$response->status(), 'latency_ms' => $latency];
        } catch (\Exception $e) {
            return ['status' => 'unreachable', 'message' => 'Cannot reach Ollama: '.$e->getMessage(), 'latency_ms' => null];
        }
    }

    /**
     * Get system info.
     */
    public function systemInfo(): array
    {
        return [
            'php_version' => PHP_VERSION,
            'laravel_version' => app()->version(),
            'app_version' => config('app.version', '1.0.0'),
            'environment' => config('app.env'),
            'debug_mode' => config('app.debug'),
            'db_driver' => config('database.default'),
            'cache_driver' => config('cache.default'),
            'queue_driver' => config('queue.default'),
            'disk_free' => disk_free_space('/') ? $this->humanFileSize((int) disk_free_space('/')) : 'unknown',
            'memory_limit' => ini_get('memory_limit'),
        ];
    }

    private function humanFileSize(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $i = 0;
        while ($bytes >= 1024 && $i < count($units) - 1) {
            $bytes /= 1024;
            $i++;
        }

        return round($bytes, 2).' '.$units[$i];
    }
}
