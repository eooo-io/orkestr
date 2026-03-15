<?php

namespace App\Services;

/**
 * Generates SDK client code from the OpenAPI spec.
 * Produces TypeScript (npm) and PHP (Composer) client stubs.
 */
class SdkGeneratorService
{
    public function __construct(
        protected OpenApiSpecService $specService,
    ) {}

    /**
     * Generate a TypeScript SDK client.
     */
    public function generateTypeScript(): string
    {
        $spec = $this->specService->generate();
        $paths = $spec['paths'] ?? [];

        $methods = [];
        foreach ($paths as $path => $operations) {
            foreach ($operations as $method => $operation) {
                $opId = $operation['operationId'] ?? $method . str_replace('/', '_', $path);
                $summary = $operation['summary'] ?? '';
                $hasBody = in_array($method, ['post', 'put', 'patch']);

                $params = [];
                foreach ($operation['parameters'] ?? [] as $param) {
                    $params[] = $param['name'];
                }

                $pathWithTemplate = preg_replace('/\{(\w+)\}/', '${$1}', $path);
                $bodyParam = $hasBody ? ', data?: Record<string, unknown>' : '';
                $bodyArg = $hasBody ? ', data' : '';

                $paramsStr = implode(', ', array_map(fn ($p) => "{$p}: string | number", $params));
                if ($paramsStr && $hasBody) {
                    $paramsStr .= $bodyParam;
                } elseif ($hasBody) {
                    $paramsStr = 'data?: Record<string, unknown>';
                    $bodyArg = ', data';
                }

                $methods[] = "  /** {$summary} */\n  async {$opId}({$paramsStr}): Promise<ApiResponse> {\n    return this.request('{$method}', `{$pathWithTemplate}`{$bodyArg});\n  }";
            }
        }

        $methodsStr = implode("\n\n", $methods);

        return <<<TS
// @eooo/sdk - Auto-generated TypeScript SDK
// Generated from OpenAPI 3.1 specification

export interface ApiResponse<T = unknown> {
  data: T;
  message?: string;
}

export interface SdkConfig {
  baseUrl: string;
  token?: string;
}

export class OrkestrClient {
  private baseUrl: string;
  private token?: string;

  constructor(config: SdkConfig) {
    this.baseUrl = config.baseUrl.replace(/\\/\$/, '');
    this.token = config.token;
  }

  private async request(method: string, path: string, data?: Record<string, unknown>): Promise<ApiResponse> {
    const headers: Record<string, string> = {
      'Content-Type': 'application/json',
      'Accept': 'application/json',
    };

    if (this.token) {
      headers['Authorization'] = `Bearer \${this.token}`;
    }

    const response = await fetch(`\${this.baseUrl}\${path}`, {
      method: method.toUpperCase(),
      headers,
      body: data ? JSON.stringify(data) : undefined,
    });

    if (!response.ok) {
      throw new Error(`API error: \${response.status} \${response.statusText}`);
    }

    return response.json();
  }

{$methodsStr}
}
TS;
    }

    /**
     * Generate a PHP SDK client.
     */
    public function generatePhp(): string
    {
        $spec = $this->specService->generate();
        $paths = $spec['paths'] ?? [];

        $methods = [];
        foreach ($paths as $path => $operations) {
            foreach ($operations as $method => $operation) {
                $opId = $operation['operationId'] ?? $method . str_replace('/', '_', $path);
                $summary = $operation['summary'] ?? '';
                $hasBody = in_array($method, ['post', 'put', 'patch']);

                $params = [];
                foreach ($operation['parameters'] ?? [] as $param) {
                    $params[] = $param['name'];
                }

                $phpPath = preg_replace('/\{(\w+)\}/', '{\$$1}', $path);
                $paramsStr = implode(', ', array_map(fn ($p) => "string \${$p}", $params));
                $bodyParam = $hasBody ? 'array $data = []' : '';
                if ($paramsStr && $bodyParam) {
                    $paramsStr .= ", {$bodyParam}";
                } elseif ($bodyParam) {
                    $paramsStr = $bodyParam;
                }

                $bodyArg = $hasBody ? ', $data' : '';

                $methods[] = "    /** {$summary} */\n    public function {$opId}({$paramsStr}): array\n    {\n        return \$this->request('{$method}', \"{$phpPath}\"{$bodyArg});\n    }";
            }
        }

        $methodsStr = implode("\n\n", $methods);

        return <<<PHP
<?php

// eooo/sdk - Auto-generated PHP SDK
// Generated from OpenAPI 3.1 specification

namespace Eooo\\Sdk;

class OrkestrClient
{
    private string \$baseUrl;
    private ?string \$token;

    public function __construct(string \$baseUrl, ?string \$token = null)
    {
        \$this->baseUrl = rtrim(\$baseUrl, '/');
        \$this->token = \$token;
    }

    private function request(string \$method, string \$path, array \$data = []): array
    {
        \$ch = curl_init(\$this->baseUrl . \$path);
        \$headers = ['Content-Type: application/json', 'Accept: application/json'];

        if (\$this->token) {
            \$headers[] = 'Authorization: Bearer ' . \$this->token;
        }

        curl_setopt_array(\$ch, [
            CURLOPT_CUSTOMREQUEST => strtoupper(\$method),
            CURLOPT_HTTPHEADER => \$headers,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 30,
        ]);

        if (!empty(\$data)) {
            curl_setopt(\$ch, CURLOPT_POSTFIELDS, json_encode(\$data));
        }

        \$response = curl_exec(\$ch);
        \$httpCode = curl_getinfo(\$ch, CURLINFO_HTTP_CODE);
        curl_close(\$ch);

        if (\$httpCode >= 400) {
            throw new \\RuntimeException("API error: HTTP {\$httpCode}");
        }

        return json_decode(\$response, true) ?? [];
    }

{$methodsStr}
}
PHP;
    }

    /**
     * Generate a Python SDK client.
     */
    public function generatePython(): string
    {
        $spec = $this->specService->generate();
        $paths = $spec['paths'] ?? [];

        $methods = [];
        foreach ($paths as $path => $operations) {
            foreach ($operations as $method => $operation) {
                $opId = $operation['operationId'] ?? $method . str_replace(['/', '-'], '_', $path);
                $summary = $operation['summary'] ?? '';
                $hasBody = in_array($method, ['post', 'put', 'patch']);

                $params = [];
                foreach ($operation['parameters'] ?? [] as $param) {
                    $params[] = $param['name'];
                }

                $pyPath = preg_replace('/\{(\w+)\}/', '{$1}', $path);
                $paramsStr = implode(', ', array_map(fn ($p) => "{$p}: str", $params));
                $bodyParam = $hasBody ? 'data: dict | None = None' : '';
                if ($paramsStr && $bodyParam) {
                    $paramsStr .= ", {$bodyParam}";
                } elseif ($bodyParam) {
                    $paramsStr = $bodyParam;
                }

                $bodyArg = $hasBody ? ', data=data' : '';

                $methods[] = "    def {$opId}(self, {$paramsStr}) -> dict:\n        \"\"\"{$summary}\"\"\"\n        return self._request(\"{$method}\", f\"{$pyPath}\"{$bodyArg})";
            }
        }

        $methodsStr = implode("\n\n", $methods);

        return <<<PYTHON
# eooo/sdk - Auto-generated Python SDK
# Generated from OpenAPI 3.1 specification

from __future__ import annotations

import json
from typing import Any
from urllib.request import Request, urlopen
from urllib.error import HTTPError


class OrkestrClient:
    \"\"\"Python client for the Orkestr API.\"\"\"

    def __init__(self, base_url: str, token: str | None = None) -> None:
        self.base_url = base_url.rstrip("/")
        self.token = token

    def _request(self, method: str, path: str, data: dict | None = None) -> dict:
        url = f"{self.base_url}{path}"
        headers = {
            "Content-Type": "application/json",
            "Accept": "application/json",
        }

        if self.token:
            headers["Authorization"] = f"Bearer {self.token}"

        body = json.dumps(data).encode() if data else None
        req = Request(url, data=body, headers=headers, method=method.upper())

        try:
            with urlopen(req, timeout=30) as resp:
                return json.loads(resp.read().decode())
        except HTTPError as e:
            raise RuntimeError(f"API error: HTTP {e.code}") from e

{$methodsStr}
PYTHON;
    }
}
