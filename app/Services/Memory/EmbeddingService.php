<?php

namespace App\Services\Memory;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class EmbeddingService
{
    private string $provider;

    private string $model;

    private int $dimension;

    public function __construct()
    {
        $this->provider = config('services.embedding.provider', env('EMBEDDING_PROVIDER', 'local'));
        $this->model = config('services.embedding.model', env('EMBEDDING_MODEL', 'text-embedding-3-small'));
        $this->dimension = $this->provider === 'openai' ? 1536 : 384;
    }

    /**
     * Generate an embedding vector for the given text.
     *
     * @return float[]
     */
    public function embed(string $text): array
    {
        if ($this->provider === 'openai' && env('OPENAI_API_KEY')) {
            return $this->embedViaOpenAI($text);
        }

        return $this->embedLocal($text);
    }

    /**
     * Get the vector dimension for the current provider.
     */
    public function getDimension(): int
    {
        return $this->dimension;
    }

    /**
     * Generate embedding via OpenAI text-embedding-3-small.
     *
     * @return float[]
     */
    private function embedViaOpenAI(string $text): array
    {
        try {
            $response = Http::withToken(env('OPENAI_API_KEY'))
                ->timeout(30)
                ->post('https://api.openai.com/v1/embeddings', [
                    'model' => $this->model,
                    'input' => mb_substr($text, 0, 8000),
                ]);

            if ($response->successful()) {
                $data = $response->json();

                return $data['data'][0]['embedding'] ?? $this->embedLocal($text);
            }

            Log::warning('OpenAI embedding failed, falling back to local', [
                'status' => $response->status(),
            ]);

            return $this->embedLocal($text);
        } catch (\Throwable $e) {
            Log::warning("OpenAI embedding error: {$e->getMessage()}, falling back to local");

            return $this->embedLocal($text);
        }
    }

    /**
     * Deterministic local hash-based embedding for development/testing.
     * Produces a 384-dimension vector based on character n-gram hashing.
     *
     * @return float[]
     */
    private function embedLocal(string $text): array
    {
        $dimension = 384;
        $vector = array_fill(0, $dimension, 0.0);
        $text = mb_strtolower(trim($text));

        if ($text === '') {
            return $vector;
        }

        // Generate n-gram hash features (bigrams and trigrams)
        $len = mb_strlen($text);
        $count = 0;

        for ($n = 2; $n <= 3; $n++) {
            for ($i = 0; $i <= $len - $n; $i++) {
                $ngram = mb_substr($text, $i, $n);
                $hash = crc32($ngram);
                $idx = abs($hash) % $dimension;
                $sign = ($hash > 0) ? 1.0 : -1.0;
                $vector[$idx] += $sign;
                $count++;
            }
        }

        // Normalize to unit vector
        if ($count > 0) {
            $magnitude = 0.0;
            foreach ($vector as $v) {
                $magnitude += $v * $v;
            }
            $magnitude = sqrt($magnitude);

            if ($magnitude > 0) {
                for ($i = 0; $i < $dimension; $i++) {
                    $vector[$i] /= $magnitude;
                }
            }
        }

        return $vector;
    }
}
