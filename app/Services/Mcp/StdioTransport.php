<?php

namespace App\Services\Mcp;

class StdioTransport implements McpTransportInterface
{
    /** @var resource|null */
    private $process = null;

    /** @var array<int, resource> */
    private array $pipes = [];

    private string $readBuffer = '';

    public function __construct(
        private readonly string $command,
        private readonly array $args = [],
        private readonly array $env = [],
        private readonly ?string $workingDirectory = null,
        private readonly int $timeoutSeconds = 30,
    ) {}

    public function connect(): void
    {
        if ($this->isConnected()) {
            return;
        }

        $cmd = implode(' ', array_map('escapeshellarg', array_merge([$this->command], $this->args)));

        $descriptors = [
            0 => ['pipe', 'r'], // stdin
            1 => ['pipe', 'w'], // stdout
            2 => ['pipe', 'w'], // stderr
        ];

        $env = ! empty($this->env)
            ? array_merge(getenv(), $this->env)
            : null;

        $this->process = @proc_open(
            $cmd,
            $descriptors,
            $this->pipes,
            $this->workingDirectory,
            $env,
        );

        if (! is_resource($this->process)) {
            throw new McpConnectionException("Failed to start MCP server: {$cmd}");
        }

        // Set stdout to non-blocking so reads don't hang
        stream_set_blocking($this->pipes[1], false);
        stream_set_blocking($this->pipes[2], false);

        // Wait briefly for process to initialize
        usleep(100_000); // 100ms

        $status = proc_get_status($this->process);
        if (! $status['running']) {
            $stderr = stream_get_contents($this->pipes[2]);
            $this->cleanup();
            throw new McpConnectionException("MCP server exited immediately: {$stderr}");
        }
    }

    public function send(McpMessage $message): ?McpResponse
    {
        if (! $this->isConnected()) {
            throw new McpConnectionException('Not connected to MCP server');
        }

        $json = $message->toJson() . "\n";

        $written = @fwrite($this->pipes[0], $json);
        if ($written === false) {
            throw new McpConnectionException('Failed to write to MCP server stdin');
        }
        @fflush($this->pipes[0]);

        if ($message->isNotification()) {
            return null;
        }

        return $this->readResponse($message->id);
    }

    public function disconnect(): void
    {
        if (! is_resource($this->process)) {
            return;
        }

        // Close stdin to signal the server to exit
        if (isset($this->pipes[0]) && is_resource($this->pipes[0])) {
            @fclose($this->pipes[0]);
        }

        // Wait briefly for graceful shutdown
        $deadline = microtime(true) + 3;
        while (microtime(true) < $deadline) {
            $status = proc_get_status($this->process);
            if (! $status['running']) {
                break;
            }
            usleep(50_000); // 50ms
        }

        $this->cleanup();
    }

    public function isConnected(): bool
    {
        if (! is_resource($this->process)) {
            return false;
        }

        $status = proc_get_status($this->process);

        return $status['running'];
    }

    /**
     * Read stderr output (useful for debugging).
     */
    public function readStderr(): string
    {
        if (! isset($this->pipes[2]) || ! is_resource($this->pipes[2])) {
            return '';
        }

        return stream_get_contents($this->pipes[2]) ?: '';
    }

    private function readResponse(string|int|null $expectedId): McpResponse
    {
        $deadline = microtime(true) + $this->timeoutSeconds;

        while (microtime(true) < $deadline) {
            // Read available data from stdout
            $chunk = @fread($this->pipes[1], 65536);
            if ($chunk !== false && $chunk !== '') {
                $this->readBuffer .= $chunk;
            }

            // Try to parse complete JSON lines from buffer
            while (($newlinePos = strpos($this->readBuffer, "\n")) !== false) {
                $line = substr($this->readBuffer, 0, $newlinePos);
                $this->readBuffer = substr($this->readBuffer, $newlinePos + 1);

                $line = trim($line);
                if ($line === '') {
                    continue;
                }

                $data = json_decode($line, true);
                if ($data === null) {
                    continue;
                }

                // Skip server-initiated notifications (no id field)
                if (! isset($data['id'])) {
                    continue;
                }

                // Match response to our request
                if ($expectedId !== null && ($data['id'] ?? null) !== $expectedId) {
                    continue;
                }

                return McpMessage::parseResponse($data);
            }

            // Check if process is still running
            if (! $this->isConnected()) {
                $stderr = $this->readStderr();
                throw new McpConnectionException("MCP server process died unexpectedly: {$stderr}");
            }

            usleep(10_000); // 10ms
        }

        throw new McpTimeoutException("Timeout waiting for MCP response (id: {$expectedId})");
    }

    private function cleanup(): void
    {
        foreach ($this->pipes as $pipe) {
            if (is_resource($pipe)) {
                @fclose($pipe);
            }
        }
        $this->pipes = [];

        if (is_resource($this->process)) {
            // Force kill if still running
            $status = proc_get_status($this->process);
            if ($status['running']) {
                // Send SIGTERM, then SIGKILL
                @proc_terminate($this->process, 15);
                usleep(500_000);
                $status = proc_get_status($this->process);
                if ($status['running']) {
                    @proc_terminate($this->process, 9);
                }
            }
            @proc_close($this->process);
        }

        $this->process = null;
        $this->readBuffer = '';
    }

    public function __destruct()
    {
        $this->disconnect();
    }
}
