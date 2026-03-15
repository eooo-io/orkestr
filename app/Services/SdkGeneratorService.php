<?php

declare(strict_types=1);

namespace App\Services;

/**
 * Generates SOLID-compliant SDK client code from the OpenAPI spec.
 *
 * Each generated SDK follows:
 * - Single Responsibility: HttpClient, ApiError, Config, and resource-grouped service classes
 * - Open/Closed: extensible via composition, new resources don't require modifying existing code
 * - Liskov Substitution: interfaces define contracts
 * - Interface Segregation: small, focused interfaces per resource group
 * - Dependency Inversion: client depends on abstractions (HttpClientInterface)
 *
 * Code style compliance:
 * - TypeScript: Google TypeScript Style Guide
 * - PHP: PSR-12
 * - Python: PEP 8
 */
class SdkGeneratorService
{
    public function __construct(
        protected OpenApiSpecService $specService,
    ) {}

    /**
     * Generate a TypeScript SDK (Google TypeScript Style Guide).
     */
    public function generateTypeScript(): string
    {
        $grouped = $this->groupOperationsByResource();
        $resourceClasses = $this->buildTypeScriptResources($grouped);

        return $this->renderTypeScript($resourceClasses, $grouped);
    }

    /**
     * Generate a PHP SDK (PSR-12).
     */
    public function generatePhp(): string
    {
        $grouped = $this->groupOperationsByResource();
        $resourceClasses = $this->buildPhpResources($grouped);

        return $this->renderPhp($resourceClasses, $grouped);
    }

    /**
     * Generate a Python SDK (PEP 8).
     */
    public function generatePython(): string
    {
        $grouped = $this->groupOperationsByResource();
        $resourceClasses = $this->buildPythonResources($grouped);

        return $this->renderPython($resourceClasses, $grouped);
    }

    // -------------------------------------------------------------------------
    // Shared: group OpenAPI paths into resource namespaces
    // -------------------------------------------------------------------------

    /**
     * @return array<string, list<array{method: string, path: string, opId: string, summary: string, hasBody: bool, params: list<string>}>>
     */
    private function groupOperationsByResource(): array
    {
        $spec = $this->specService->generate();
        $paths = $spec['paths'] ?? [];
        $groups = [];

        foreach ($paths as $path => $operations) {
            $resource = $this->extractResourceName($path);

            foreach ($operations as $method => $operation) {
                if (! is_array($operation)) {
                    continue;
                }

                $opId = $operation['operationId']
                    ?? $this->generateOperationId($method, $path);
                $summary = $operation['summary'] ?? '';
                $hasBody = in_array($method, ['post', 'put', 'patch'], true);

                $params = [];
                foreach ($operation['parameters'] ?? [] as $param) {
                    if (isset($param['name'])) {
                        $params[] = $param['name'];
                    }
                }

                $groups[$resource][] = [
                    'method' => $method,
                    'path' => $path,
                    'opId' => $opId,
                    'summary' => $summary,
                    'hasBody' => $hasBody,
                    'params' => $params,
                ];
            }
        }

        ksort($groups);

        return $groups;
    }

    private function extractResourceName(string $path): string
    {
        $segments = explode('/', trim($path, '/'));
        // Skip 'api' prefix
        $meaningful = array_values(array_filter(
            $segments,
            fn (string $s) => $s !== 'api' && ! str_starts_with($s, '{'),
        ));

        $name = $meaningful[0] ?? 'general';

        return ucfirst(str_replace('-', '', ucwords($name, '-')));
    }

    private function generateOperationId(string $method, string $path): string
    {
        $cleaned = str_replace(['{', '}'], '', $path);
        $parts = array_filter(explode('/', $cleaned), fn (string $s) => $s !== '' && $s !== 'api');

        return $method . implode('', array_map('ucfirst', $parts));
    }

    // -------------------------------------------------------------------------
    // TypeScript — Google TypeScript Style Guide
    // -------------------------------------------------------------------------

    /**
     * @param array<string, list<array<string, mixed>>> $grouped
     * @return array<string, string>
     */
    private function buildTypeScriptResources(array $grouped): array
    {
        $classes = [];

        foreach ($grouped as $resource => $operations) {
            $methods = [];

            foreach ($operations as $op) {
                $pathTemplate = preg_replace('/\{(\w+)\}/', '${$1}', $op['path']);
                $paramList = [];

                foreach ($op['params'] as $p) {
                    $paramList[] = "{$p}: string | number";
                }

                if ($op['hasBody']) {
                    $paramList[] = 'data?: Record<string, unknown>';
                }

                $paramsStr = implode(', ', $paramList);
                $bodyArg = $op['hasBody'] ? ', data' : '';
                $summary = $op['summary'] ?: ucfirst($op['opId']);

                $methods[] = <<<METHOD
  /** {$summary} */
  async {$op['opId']}({$paramsStr}): Promise<ApiResponse> {
    return this.httpClient.request('{$op['method']}', `{$pathTemplate}`{$bodyArg});
  }
METHOD;
            }

            $methodsStr = implode("\n\n", $methods);
            $classes[$resource] = <<<CLS
/**
 * {$resource} API operations.
 *
 * Single responsibility: encapsulates all {$resource}-related endpoints.
 */
export class {$resource}Api {
  constructor(private readonly httpClient: HttpClientInterface) {}

{$methodsStr}
}
CLS;
        }

        return $classes;
    }

    /**
     * @param array<string, string> $resourceClasses
     * @param array<string, list<array<string, mixed>>> $grouped
     */
    private function renderTypeScript(array $resourceClasses, array $grouped): string
    {
        $classesStr = implode("\n\n", $resourceClasses);
        $properties = [];
        $assignments = [];

        foreach (array_keys($grouped) as $resource) {
            $propName = lcfirst($resource);
            $properties[] = "  readonly {$propName}: {$resource}Api;";
            $assignments[] = "    this.{$propName} = new {$resource}Api(this.httpClient);";
        }

        $propertiesStr = implode("\n", $properties);
        $assignmentsStr = implode("\n", $assignments);

        return <<<TS
/**
 * @eooo/orkestr-sdk — Auto-generated TypeScript SDK
 * Generated from OpenAPI 3.1 specification
 *
 * Code style: Google TypeScript Style Guide
 * Architecture: SOLID — Interface Segregation via resource classes,
 *   Dependency Inversion via HttpClientInterface
 */

// ---------------------------------------------------------------------------
// Types
// ---------------------------------------------------------------------------

/** Standard API envelope returned by all endpoints. */
export interface ApiResponse<T = unknown> {
  data: T;
  message?: string;
}

/** SDK configuration options. */
export interface SdkConfig {
  /** Base URL of the Orkestr API (e.g. "https://orkestr.example.com/api"). */
  baseUrl: string;
  /** Bearer token for authentication. */
  token?: string;
  /** Request timeout in milliseconds. Defaults to 30000. */
  timeoutMs?: number;
}

// ---------------------------------------------------------------------------
// Error handling (Single Responsibility)
// ---------------------------------------------------------------------------

/** Typed error for API failures. */
export class OrkestrApiError extends Error {
  constructor(
    message: string,
    public readonly statusCode: number,
    public readonly responseBody?: string,
  ) {
    super(message);
    this.name = 'OrkestrApiError';
  }
}

// ---------------------------------------------------------------------------
// HTTP client interface (Dependency Inversion)
// ---------------------------------------------------------------------------

/** Abstraction for HTTP transport — allows swapping fetch for testing. */
export interface HttpClientInterface {
  request(
    method: string,
    path: string,
    data?: Record<string, unknown>,
  ): Promise<ApiResponse>;
}

/** Default HTTP client backed by the Fetch API. */
export class FetchHttpClient implements HttpClientInterface {
  private readonly baseUrl: string;
  private readonly token?: string;
  private readonly timeoutMs: number;

  constructor(config: SdkConfig) {
    this.baseUrl = config.baseUrl.replace(/\\/\$/, '');
    this.token = config.token;
    this.timeoutMs = config.timeoutMs ?? 30_000;
  }

  async request(
    method: string,
    path: string,
    data?: Record<string, unknown>,
  ): Promise<ApiResponse> {
    const headers: Record<string, string> = {
      'Content-Type': 'application/json',
      'Accept': 'application/json',
    };

    if (this.token) {
      headers['Authorization'] = `Bearer \${this.token}`;
    }

    const controller = new AbortController();
    const timer = setTimeout(() => controller.abort(), this.timeoutMs);

    try {
      const response = await fetch(`\${this.baseUrl}\${path}`, {
        method: method.toUpperCase(),
        headers,
        body: data ? JSON.stringify(data) : undefined,
        signal: controller.signal,
      });

      if (!response.ok) {
        const body = await response.text().catch(() => '');
        throw new OrkestrApiError(
          `HTTP \${response.status}: \${response.statusText}`,
          response.status,
          body,
        );
      }

      return await response.json();
    } finally {
      clearTimeout(timer);
    }
  }
}

// ---------------------------------------------------------------------------
// Resource API classes (Interface Segregation + Single Responsibility)
// ---------------------------------------------------------------------------

{$classesStr}

// ---------------------------------------------------------------------------
// Facade client (Open/Closed — add resources without modifying existing code)
// ---------------------------------------------------------------------------

/**
 * Orkestr API client.
 *
 * Access resource-specific APIs via properties (e.g. `client.projects`).
 * Follows the facade pattern: composes resource classes over a shared HTTP client.
 */
export class OrkestrClient {
  private readonly httpClient: HttpClientInterface;

{$propertiesStr}

  constructor(config: SdkConfig, httpClient?: HttpClientInterface) {
    this.httpClient = httpClient ?? new FetchHttpClient(config);

{$assignmentsStr}
  }
}
TS;
    }

    // -------------------------------------------------------------------------
    // PHP — PSR-12
    // -------------------------------------------------------------------------

    /**
     * @param array<string, list<array<string, mixed>>> $grouped
     * @return array<string, string>
     */
    private function buildPhpResources(array $grouped): array
    {
        $classes = [];

        foreach ($grouped as $resource => $operations) {
            $methods = [];

            foreach ($operations as $op) {
                $phpPath = preg_replace('/\{(\w+)\}/', '{\$$1}', $op['path']);
                $paramList = [];

                foreach ($op['params'] as $p) {
                    $paramList[] = "string \${$p}";
                }

                if ($op['hasBody']) {
                    $paramList[] = 'array $data = []';
                }

                $paramsStr = implode(', ', $paramList);
                $bodyArg = $op['hasBody'] ? ', $data' : '';
                $summary = $op['summary'] ?: ucfirst($op['opId']);

                $methods[] = <<<METHOD
    /**
     * {$summary}
     *
     * @return array<string, mixed>
     */
    public function {$op['opId']}({$paramsStr}): array
    {
        return \$this->httpClient->request('{$op['method']}', \"{$phpPath}\"{$bodyArg});
    }
METHOD;
            }

            $methodsStr = implode("\n\n", $methods);
            $classes[$resource] = <<<CLS
/**
 * {$resource} API resource.
 *
 * Single Responsibility: encapsulates all {$resource}-related API calls.
 */
class {$resource}Api
{
    public function __construct(
        private readonly HttpClientInterface \$httpClient,
    ) {}

{$methodsStr}
}
CLS;
        }

        return $classes;
    }

    /**
     * @param array<string, string> $resourceClasses
     * @param array<string, list<array<string, mixed>>> $grouped
     */
    private function renderPhp(array $resourceClasses, array $grouped): string
    {
        $classesStr = implode("\n\n", $resourceClasses);
        $properties = [];
        $assignments = [];
        $accessors = [];

        foreach (array_keys($grouped) as $resource) {
            $propName = lcfirst($resource);
            $properties[] = "    public readonly {$resource}Api \${$propName};";
            $assignments[] = "        \$this->{$propName} = new {$resource}Api(\$this->httpClient);";
            $accessors[] = " * @property-read {$resource}Api \${$propName}";
        }

        $propertiesStr = implode("\n", $properties);
        $assignmentsStr = implode("\n", $assignments);

        return <<<'PHPHEAD'
<?php

declare(strict_types=1);

/**
 * eooo/orkestr-sdk — Auto-generated PHP SDK.
 *
 * Generated from OpenAPI 3.1 specification.
 *
 * Code style: PSR-12
 * Architecture: SOLID — Interface Segregation via resource classes,
 *   Dependency Inversion via HttpClientInterface
 */

namespace Eooo\Sdk;

// ---------------------------------------------------------------------------
// Exceptions (Single Responsibility)
// ---------------------------------------------------------------------------

/**
 * Base exception for all SDK errors.
 */
class OrkestrException extends \RuntimeException
{
}

/**
 * Thrown when the API returns an HTTP error (4xx/5xx).
 */
class ApiException extends OrkestrException
{
    public function __construct(
        string $message,
        public readonly int $statusCode,
        public readonly string $responseBody = '',
        ?\Throwable $previous = null,
    ) {
        parent::__construct($message, $statusCode, $previous);
    }
}

// ---------------------------------------------------------------------------
// Configuration (Single Responsibility)
// ---------------------------------------------------------------------------

/**
 * Immutable SDK configuration.
 */
final class SdkConfig
{
    public function __construct(
        public readonly string $baseUrl,
        public readonly ?string $token = null,
        public readonly int $timeoutSeconds = 30,
    ) {}
}

// ---------------------------------------------------------------------------
// HTTP client abstraction (Dependency Inversion)
// ---------------------------------------------------------------------------

/**
 * Contract for HTTP transport — swap implementations for testing.
 */
interface HttpClientInterface
{
    /**
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     *
     * @throws ApiException
     */
    public function request(string $method, string $path, array $data = []): array;
}

/**
 * Default HTTP client using cURL.
 */
class CurlHttpClient implements HttpClientInterface
{
    private readonly string $baseUrl;
    private readonly ?string $token;
    private readonly int $timeout;

    public function __construct(SdkConfig $config)
    {
        $this->baseUrl = rtrim($config->baseUrl, '/');
        $this->token = $config->token;
        $this->timeout = $config->timeoutSeconds;
    }

    /**
     * @inheritDoc
     */
    public function request(string $method, string $path, array $data = []): array
    {
        $ch = curl_init($this->baseUrl . $path);

        if ($ch === false) {
            throw new OrkestrException('Failed to initialize cURL handle.');
        }

        $headers = [
            'Content-Type: application/json',
            'Accept: application/json',
        ];

        if ($this->token !== null) {
            $headers[] = 'Authorization: Bearer ' . $this->token;
        }

        curl_setopt_array($ch, [
            CURLOPT_CUSTOMREQUEST => strtoupper($method),
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => $this->timeout,
        ]);

        if (!empty($data)) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data, JSON_THROW_ON_ERROR));
        }

        $response = curl_exec($ch);
        $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);

        if ($response === false) {
            throw new OrkestrException('cURL request failed: ' . $curlError);
        }

        if ($httpCode >= 400) {
            throw new ApiException(
                "API error: HTTP {$httpCode}",
                $httpCode,
                is_string($response) ? $response : '',
            );
        }

        return json_decode((string) $response, true, 512, JSON_THROW_ON_ERROR) ?? [];
    }
}

PHPHEAD
. "\n// ---------------------------------------------------------------------------\n"
. "// Resource API classes (Interface Segregation + Single Responsibility)\n"
. "// ---------------------------------------------------------------------------\n\n"
. $classesStr
. "\n\n// ---------------------------------------------------------------------------\n"
. "// Facade client (Open/Closed — add resources without modifying existing code)\n"
. "// ---------------------------------------------------------------------------\n\n"
. <<<PHPCLIENT
/**
 * Orkestr API client.
 *
 * Access resource APIs via public readonly properties (e.g. \$client->projects).
 */
class OrkestrClient
{
    private readonly HttpClientInterface \$httpClient;

{$propertiesStr}

    public function __construct(SdkConfig \$config, ?HttpClientInterface \$httpClient = null)
    {
        \$this->httpClient = \$httpClient ?? new CurlHttpClient(\$config);

{$assignmentsStr}
    }
}
PHPCLIENT;
    }

    // -------------------------------------------------------------------------
    // Python — PEP 8
    // -------------------------------------------------------------------------

    /**
     * @param array<string, list<array<string, mixed>>> $grouped
     * @return array<string, string>
     */
    private function buildPythonResources(array $grouped): array
    {
        $classes = [];

        foreach ($grouped as $resource => $operations) {
            $methods = [];
            $snakeResource = $this->toSnakeCase($resource);

            foreach ($operations as $op) {
                $pyPath = $op['path'];
                $snakeOp = $this->toSnakeCase($op['opId']);
                $paramList = ['self'];

                foreach ($op['params'] as $p) {
                    $paramList[] = "{$p}: str";
                }

                if ($op['hasBody']) {
                    $paramList[] = 'data: dict[str, Any] | None = None';
                }

                $paramsStr = implode(', ', $paramList);
                $bodyArg = $op['hasBody'] ? ', data=data' : '';
                $summary = $op['summary'] ?: ucfirst($op['opId']);

                $methods[] = <<<METHOD
    def {$snakeOp}({$paramsStr}) -> dict[str, Any]:
        \"\"\"{$summary}\"\"\"
        return self._http_client.request("{$op['method']}", f"{$pyPath}"{$bodyArg})
METHOD;
            }

            $methodsStr = implode("\n\n", $methods);
            $classes[$resource] = <<<CLS

class {$resource}Api:
    \"\"\"
    {$resource} API resource.

    Single Responsibility: encapsulates all {$resource}-related API calls.
    \"\"\"

    def __init__(self, http_client: "HttpClientProtocol") -> None:
        self._http_client = http_client

{$methodsStr}
CLS;
        }

        return $classes;
    }

    /**
     * @param array<string, string> $resourceClasses
     * @param array<string, list<array<string, mixed>>> $grouped
     */
    private function renderPython(array $resourceClasses, array $grouped): string
    {
        $classesStr = implode("\n\n", $resourceClasses);
        $properties = [];
        $assignments = [];

        foreach (array_keys($grouped) as $resource) {
            $snakeProp = $this->toSnakeCase($resource);
            $properties[] = "        self.{$snakeProp}: {$resource}Api = {$resource}Api(self._http_client)";
        }

        $assignmentsStr = implode("\n", $properties);

        return <<<PYTHON
"""
eooo/orkestr-sdk — Auto-generated Python SDK.

Generated from OpenAPI 3.1 specification.

Code style: PEP 8
Architecture: SOLID — Interface Segregation via resource classes,
  Dependency Inversion via HttpClientProtocol (typing.Protocol)
"""

from __future__ import annotations

import json
from typing import Any, Protocol
from urllib.error import HTTPError
from urllib.request import Request, urlopen


# ---------------------------------------------------------------------------
# Exceptions (Single Responsibility)
# ---------------------------------------------------------------------------


class OrkestrError(Exception):
    """Base exception for all SDK errors."""


class ApiError(OrkestrError):
    """Raised when the API returns an HTTP error (4xx/5xx)."""

    def __init__(
        self,
        message: str,
        status_code: int,
        response_body: str = "",
    ) -> None:
        super().__init__(message)
        self.status_code = status_code
        self.response_body = response_body


# ---------------------------------------------------------------------------
# Configuration (Single Responsibility)
# ---------------------------------------------------------------------------


class SdkConfig:
    """Immutable SDK configuration."""

    __slots__ = ("base_url", "token", "timeout_seconds")

    def __init__(
        self,
        base_url: str,
        token: str | None = None,
        timeout_seconds: int = 30,
    ) -> None:
        self.base_url = base_url.rstrip("/")
        self.token = token
        self.timeout_seconds = timeout_seconds


# ---------------------------------------------------------------------------
# HTTP client protocol (Dependency Inversion)
# ---------------------------------------------------------------------------


class HttpClientProtocol(Protocol):
    """Contract for HTTP transport — swap implementations for testing."""

    def request(
        self,
        method: str,
        path: str,
        data: dict[str, Any] | None = None,
    ) -> dict[str, Any]: ...


class UrllibHttpClient:
    """Default HTTP client using urllib (stdlib, no dependencies)."""

    def __init__(self, config: SdkConfig) -> None:
        self._base_url = config.base_url
        self._token = config.token
        self._timeout = config.timeout_seconds

    def request(
        self,
        method: str,
        path: str,
        data: dict[str, Any] | None = None,
    ) -> dict[str, Any]:
        \"\"\"Execute an HTTP request and return the parsed JSON response.\"\"\"
        url = f"{self._base_url}{path}"
        headers = {
            "Content-Type": "application/json",
            "Accept": "application/json",
        }

        if self._token:
            headers["Authorization"] = f"Bearer {self._token}"

        body = json.dumps(data).encode("utf-8") if data else None
        req = Request(url, data=body, headers=headers, method=method.upper())

        try:
            with urlopen(req, timeout=self._timeout) as resp:
                return json.loads(resp.read().decode("utf-8"))
        except HTTPError as exc:
            response_body = ""
            if exc.fp is not None:
                response_body = exc.fp.read().decode("utf-8", errors="replace")
            raise ApiError(
                f"API error: HTTP {exc.code}",
                status_code=exc.code,
                response_body=response_body,
            ) from exc


# ---------------------------------------------------------------------------
# Resource API classes (Interface Segregation + Single Responsibility)
# ---------------------------------------------------------------------------

{$classesStr}


# ---------------------------------------------------------------------------
# Facade client (Open/Closed — add resources without modifying existing code)
# ---------------------------------------------------------------------------


class OrkestrClient:
    \"\"\"
    Orkestr API client.

    Access resource APIs via properties (e.g. ``client.projects``).
    Follows the facade pattern: composes resource classes over a shared
    HTTP client.
    \"\"\"

    def __init__(
        self,
        config: SdkConfig,
        http_client: HttpClientProtocol | None = None,
    ) -> None:
        self._http_client = http_client or UrllibHttpClient(config)

{$assignmentsStr}
PYTHON;
    }

    private function toSnakeCase(string $input): string
    {
        return strtolower(preg_replace('/(?<!^)[A-Z]/', '_$0', $input));
    }
}
