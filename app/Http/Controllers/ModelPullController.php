<?php

namespace App\Http\Controllers;

use App\Models\AppSetting;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ModelPullController extends Controller
{
    protected function ollamaBaseUrl(): string
    {
        return rtrim(
            AppSetting::get('ollama_url') ?: env('OLLAMA_URL', 'http://localhost:11434'),
            '/',
        );
    }

    /**
     * Start pulling an Ollama model. Streams SSE progress events.
     */
    public function pull(Request $request): StreamedResponse
    {
        $validated = $request->validate([
            'model' => 'required|string|max:255|regex:/^[a-zA-Z0-9._\/:@-]+$/',
        ]);

        $model = $validated['model'];
        $baseUrl = $this->ollamaBaseUrl();

        // Verify Ollama is reachable
        try {
            $ch = curl_init("{$baseUrl}/api/tags");
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT => 5,
                CURLOPT_CONNECTTIMEOUT => 3,
            ]);
            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            if ($httpCode !== 200) {
                return new StreamedResponse(function () {
                    echo "data: " . json_encode(['error' => 'Ollama is not reachable']) . "\n\n";
                    ob_flush();
                    flush();
                }, 200, [
                    'Content-Type' => 'text/event-stream',
                    'Cache-Control' => 'no-cache',
                    'X-Accel-Buffering' => 'no',
                ]);
            }
        } catch (\Throwable) {
            return new StreamedResponse(function () {
                echo "data: " . json_encode(['error' => 'Ollama is not reachable']) . "\n\n";
                ob_flush();
                flush();
            }, 200, [
                'Content-Type' => 'text/event-stream',
                'Cache-Control' => 'no-cache',
                'X-Accel-Buffering' => 'no',
            ]);
        }

        // Track active pull
        $this->addPullingModel($model);

        return new StreamedResponse(function () use ($baseUrl, $model) {
            $ch = curl_init("{$baseUrl}/api/pull");
            curl_setopt_array($ch, [
                CURLOPT_POST => true,
                CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
                CURLOPT_POSTFIELDS => json_encode(['name' => $model, 'stream' => true]),
                CURLOPT_RETURNTRANSFER => false,
                CURLOPT_TIMEOUT => 3600, // 1 hour for large models
                CURLOPT_CONNECTTIMEOUT => 10,
            ]);

            $buffer = '';
            curl_setopt($ch, CURLOPT_WRITEFUNCTION, function ($ch, $data) use (&$buffer, $model) {
                $buffer .= $data;

                // Process complete lines
                while (($pos = strpos($buffer, "\n")) !== false) {
                    $line = substr($buffer, 0, $pos);
                    $buffer = substr($buffer, $pos + 1);

                    $line = trim($line);
                    if (empty($line)) {
                        continue;
                    }

                    $json = json_decode($line, true);
                    if (! $json) {
                        continue;
                    }

                    $event = [
                        'status' => $json['status'] ?? 'unknown',
                        'model' => $model,
                    ];

                    if (isset($json['completed'])) {
                        $event['completed'] = $json['completed'];
                    }
                    if (isset($json['total'])) {
                        $event['total'] = $json['total'];
                    }
                    if (isset($json['error'])) {
                        $event['error'] = $json['error'];
                    }

                    echo "data: " . json_encode($event) . "\n\n";
                    if (ob_get_level() > 0) {
                        ob_flush();
                    }
                    flush();
                }

                return strlen($data);
            });

            curl_exec($ch);
            $curlError = curl_error($ch);
            curl_close($ch);

            // Remove from pulling list
            $this->removePullingModel($model);

            if ($curlError) {
                echo "data: " . json_encode([
                    'status' => 'error',
                    'model' => $model,
                    'error' => 'Pull failed: ' . $curlError,
                ]) . "\n\n";
            } else {
                echo "data: " . json_encode([
                    'status' => 'success',
                    'model' => $model,
                ]) . "\n\n";
            }

            if (ob_get_level() > 0) {
                ob_flush();
            }
            flush();
        }, 200, [
            'Content-Type' => 'text/event-stream',
            'Cache-Control' => 'no-cache',
            'X-Accel-Buffering' => 'no',
        ]);
    }

    /**
     * Delete a model from Ollama.
     */
    public function destroy(Request $request, string $name): JsonResponse
    {
        $baseUrl = $this->ollamaBaseUrl();

        $ch = curl_init("{$baseUrl}/api/delete");
        curl_setopt_array($ch, [
            CURLOPT_CUSTOMREQUEST => 'DELETE',
            CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
            CURLOPT_POSTFIELDS => json_encode(['name' => $name]),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_CONNECTTIMEOUT => 5,
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);

        if ($curlError) {
            return response()->json(['error' => 'Ollama is not reachable: ' . $curlError], 502);
        }

        if ($httpCode >= 400) {
            $decoded = json_decode($response, true);
            $errorMsg = $decoded['error'] ?? "Failed to delete model (HTTP {$httpCode})";

            return response()->json(['error' => $errorMsg], $httpCode);
        }

        return response()->json(['message' => "Model '{$name}' deleted successfully"]);
    }

    /**
     * Get list of currently pulling models.
     */
    public function pulling(): JsonResponse
    {
        $models = Cache::get('ollama_pulling_models', []);

        return response()->json(['data' => array_values($models)]);
    }

    protected function addPullingModel(string $model): void
    {
        $models = Cache::get('ollama_pulling_models', []);
        $models[$model] = [
            'model' => $model,
            'started_at' => now()->toIso8601String(),
        ];
        Cache::put('ollama_pulling_models', $models, 3600);
    }

    protected function removePullingModel(string $model): void
    {
        $models = Cache::get('ollama_pulling_models', []);
        unset($models[$model]);
        Cache::put('ollama_pulling_models', $models, 3600);
    }
}
