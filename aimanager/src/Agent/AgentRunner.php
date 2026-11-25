<?php

namespace AIManager\Agent;

use AIManager\LLM\GeminiClient;
use AIManager\MCP\McpGeminiAdapter;
use RuntimeException;
use Throwable;

class AgentRunner
{
    public function __construct(
        private GeminiClient $gemini,
        private McpGeminiAdapter $mcpAdapter,
        private int $maxIterations = 10
    ) {
        if ($this->maxIterations < 1) {
            throw new RuntimeException('Max iterations must be at least 1.');
        }
    }

    /**
     * Uruchamia agenta i wykonuje pętlę Gemini <-> MCP.
     */
    public function run(
        string $model,
        string $systemPrompt,
        string $userPrompt,
        array $options = []
    ): array {
        if (trim($userPrompt) === '') {
            throw new RuntimeException('User prompt cannot be empty.');
        }

        // Pobieramy aktualne narzędzia bezpośrednio z MCP.
        $functionDeclarations = $this->mcpAdapter->getFunctionDeclarations();

        if ($functionDeclarations === []) {
            throw new RuntimeException('MCP returned no available tools.');
        }

        // Początkowa wiadomość użytkownika.
        $contents = [
            [
                'role' => 'user',
                'parts' => [
                    ['text' => $userPrompt],
                ],
            ],
        ];

        $toolCallsCount = 0;
        $iterations = 0;
        $usageHistory = [];
        $lastResponse = null;

        while ($iterations < $this->maxIterations) {
            $iterations++;

            // Zapytanie do Gemini.
            $response = $this->gemini->generateWithTools(
                $model,
                $systemPrompt,
                $contents,
                $functionDeclarations,
                $options
            );

            $lastResponse = $response;

            if (isset($response['usage'])) {
                $usageHistory[] = $response['usage'];
            }

            $functionCalls = $response['function_calls'] ?? [];

            // Brak functionCall oznacza odpowiedź końcową.
            if ($functionCalls === []) {
                return [
                    'success' => true,
                    'text' => $response['text'] ?? '',
                    'model' => $response['model'] ?? $model,
                    'iterations' => $iterations,
                    'tool_calls' => $toolCallsCount,
                    'usage' => $usageHistory,
                    'finish_reason' => $response['finish_reason'] ?? null,
                ];
            }

            // Zachowujemy pełną odpowiedź modelu.
            // Nie wolno usuwać thoughtSignature ani innych pól.
            $modelContent = $response['content'] ?? null;

            if (!is_array($modelContent) || $modelContent === []) {
                throw new RuntimeException(
                    'Gemini returned function calls without model content.'
                );
            }

            $contents[] = $modelContent;

            // Najpierw wykonujemy wszystkie funkcje.
            // Dopiero potem odsyłamy wszystkie wyniki.
            $functionResponseParts = [];

            foreach ($functionCalls as $functionCall) {
                try {
                    $result = $this->mcpAdapter->executeFunction(
                        $functionCall
                    );

                    $name = $result['name'];
                    $toolResponse = $result['response'];

                    $toolCallsCount++;
                } catch (Throwable $e) {
                    $name = $functionCall['name'] ?? null;

                    if (!is_string($name) || $name === '') {
                        throw new RuntimeException(
                            'Gemini returned an invalid function name.',
                            0,
                            $e
                        );
                    }

                    // Przekazujemy błąd do Gemini.
                    // Model może spróbować innego działania.
                    $toolResponse = [
                        'error' => $e->getMessage(),
                    ];
                }

                // FunctionResponse musi zawierać obiekt JSON.
                if (!is_array($toolResponse)) {
                    $toolResponse = [
                        'result' => $toolResponse,
                    ];
                }

                if (array_is_list($toolResponse)) {
                    $toolResponse = [
                        'result' => $toolResponse,
                    ];
                }

                $functionResponseParts[] = [
                    'functionResponse' => [
                        'name' => $name,
                        'response' => (object) $toolResponse,
                    ],
                ];
            }

            // Jedna wiadomość z wynikami wszystkich wywołań.
            $contents[] = [
                'role' => 'user',
                'parts' => $functionResponseParts,
            ];
        }

        // Agent przekroczył limit iteracji.
        return [
            'success' => false,
            'text' => $lastResponse['text'] ?? '',
            'model' => $lastResponse['model'] ?? $model,
            'iterations' => $iterations,
            'tool_calls' => $toolCallsCount,
            'usage' => $usageHistory,
            'finish_reason' => $lastResponse['finish_reason'] ?? null,
            'error' => 'Maximum agent iterations exceeded.',
        ];
    }
}
