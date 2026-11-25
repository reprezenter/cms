<?php

namespace AIManager\MCP;

use RuntimeException;

class McpClient
{
    private $process = null;
    private array $pipes = [];
    private int $requestId = 0;
    private bool $initialized = false;
    private int $timeout;

    public function __construct(
        private string $serverScript,
        int $timeout = 30
    ) {
        $this->timeout = $timeout;
    }

    /**
     * Uruchamia serwer MCP i inicjalizuje połączenie.
     */
    public function connect(): void
    {
        if ($this->process !== null) {
            return;
        }

        if (!is_file($this->serverScript)) {
            throw new RuntimeException(
                'MCP server script not found: ' . $this->serverScript
            );
        }

        $descriptorSpec = [
            0 => ['pipe', 'r'],
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ];

        $this->process = proc_open(
            ['php', $this->serverScript],
            $descriptorSpec,
            $this->pipes
        );

        if (!is_resource($this->process)) {
            $this->process = null;
            throw new RuntimeException('Cannot start MCP server.');
        }

        stream_set_blocking($this->pipes[0], true);
        stream_set_blocking($this->pipes[1], false);
        stream_set_blocking($this->pipes[2], false);

        $response = $this->request('initialize', [
            'protocolVersion' => '2025-11-25',
            'capabilities' => new \stdClass(),
            'clientInfo' => [
                'name' => 'aimanager',
                'version' => '1.0.0',
            ],
        ]);

        $version = $response['protocolVersion'] ?? null;

        if ($version !== '2025-11-25') {
            throw new RuntimeException(
                'Unsupported MCP protocol version: ' . ($version ?? 'unknown')
            );
        }

        $this->notify('notifications/initialized');

        $this->initialized = true;
    }

    /**
     * Pobiera listę narzędzi MCP.
     */
    public function listTools(): array
    {
        $this->ensureInitialized();

        $response = $this->request('tools/list');

        return $response['tools'] ?? [];
    }

    /**
     * Wywołuje narzędzie MCP.
     */
    public function callTool(string $name, array $arguments = []): array
    {
        $this->ensureInitialized();

        return $this->request('tools/call', [
            'name' => $name,
            'arguments' => $arguments,
        ]);
    }

    /**
     * Wysyła żądanie JSON-RPC i oczekuje odpowiedzi.
     */
    private function request(string $method, array $params = []): array
    {
        $id = ++$this->requestId;

        $message = [
            'jsonrpc' => '2.0',
            'id' => $id,
            'method' => $method,
            'params' => (object) $params,
        ];

        $this->writeMessage($message);

        while (true) {
            $response = $this->readMessage();

            if (!isset($response['id']) || $response['id'] !== $id) {
                continue;
            }

            if (isset($response['error'])) {
                throw new RuntimeException(
                    'MCP error: ' .
                    ($response['error']['message'] ?? 'Unknown error')
                );
            }

            return $response['result'] ?? [];
        }
    }

    /**
     * Wysyła powiadomienie JSON-RPC bez oczekiwania na odpowiedź.
     */
    private function notify(string $method, array $params = []): void
    {
        $this->writeMessage([
            'jsonrpc' => '2.0',
            'method' => $method,
            'params' => (object) $params,
        ]);
    }

    /**
     * Zapisuje wiadomość do STDIN serwera.
     */
    private function writeMessage(array $message): void
    {
        if (!isset($this->pipes[0]) || !is_resource($this->pipes[0])) {
            throw new RuntimeException('MCP input pipe is unavailable.');
        }

        $json = json_encode(
            $message,
            JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE
        ) . "\n";

        $length = strlen($json);
        $written = 0;

        while ($written < $length) {
            $result = fwrite(
                $this->pipes[0],
                substr($json, $written)
            );

            if ($result === false || $result === 0) {
                throw new RuntimeException('Cannot write to MCP server.');
            }

            $written += $result;
        }

        fflush($this->pipes[0]);
    }

    /**
     * Odczytuje jedną linię JSON z STDOUT serwera.
     * Obsługuje timeout i diagnostykę STDERR.
     */
    private function readMessage(): array
    {
        $buffer = '';
        $deadline = microtime(true) + $this->timeout;

        while (microtime(true) < $deadline) {
            $read = [$this->pipes[1], $this->pipes[2]];
            $write = null;
            $except = null;

            $remaining = max(0, $deadline - microtime(true));

            $seconds = (int) $remaining;
            $microseconds = (int) (($remaining - $seconds) * 1_000_000);

            $ready = @stream_select(
                $read,
                $write,
                $except,
                $seconds,
                $microseconds
            );

            if ($ready === false) {
                throw new RuntimeException('MCP stream_select failed.');
            }

            if ($ready === 0) {
                break;
            }

            foreach ($read as $stream) {
                $chunk = fread($stream, 8192);

                if ($chunk === false || $chunk === '') {
                    continue;
                }

                if ($stream === $this->pipes[2]) {
                    error_log('MCP STDERR: ' . trim($chunk));
                    continue;
                }

                $buffer .= $chunk;

                $newline = strpos($buffer, "\n");

                if ($newline !== false) {
                    $line = substr($buffer, 0, $newline);

                    if ($line === '') {
                        throw new RuntimeException('Empty MCP response.');
                    }

                    return json_decode(
                        $line,
                        true,
                        512,
                        JSON_THROW_ON_ERROR
                    );
                }
            }

            $status = proc_get_status($this->process);

            if (!$status['running']) {
                throw new RuntimeException(
                    'MCP server terminated unexpectedly.'
                );
            }
        }

        throw new RuntimeException(
            'MCP server response timeout after ' . $this->timeout . ' seconds.'
        );
    }

    private function ensureInitialized(): void
    {
        if (!$this->initialized) {
            throw new RuntimeException('MCP client is not initialized.');
        }
    }

    /**
     * Zamyka proces MCP.
     */
    public function close(): void
    {
        $this->initialized = false;

        if ($this->process === null) {
            return;
        }

        foreach ($this->pipes as $pipe) {
            if (is_resource($pipe)) {
                fclose($pipe);
            }
        }

        $status = proc_get_status($this->process);

        if ($status['running']) {
            proc_terminate($this->process);
        }

        proc_close($this->process);

        $this->pipes = [];
        $this->process = null;
    }

    public function __destruct()
    {
        $this->close();
    }
}
