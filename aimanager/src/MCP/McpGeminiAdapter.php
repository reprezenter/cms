<?php

namespace AIManager\MCP;

use RuntimeException;

class McpGeminiAdapter {

    public function __construct(
            private McpClient $client
    ) {
        
    }

    /**
     * Pobiera narzędzia MCP i konwertuje je do formatu Gemini.
     */
    public function getFunctionDeclarations(): array {
        $tools = $this->client->listTools();
        $declarations = [];

        foreach ($tools as $tool) {
            if (empty($tool['name'])) {
                continue;
            }

            $declaration = [
                'name' => $tool['name'],
                'description' => $tool['description'] ?? '',
                'parameters' => $this->convertSchema(
                        $tool['inputSchema'] ?? []
                ),
            ];

            $declarations[] = $declaration;
        }

        return $declarations;
    }

    /**
     * Konwertuje schemat JSON MCP na schemat akceptowany przez Gemini.
     */
    private function convertSchema(array $schema): array {
        $result = [];

        if (isset($schema['type'])) {
            $result['type'] = $schema['type'];
        }

        if (isset($schema['description'])) {
            $result['description'] = $schema['description'];
        }

        if (isset($schema['properties'])) {
            if ($schema['properties'] === []) {
                $result['properties'] = new \stdClass();
            } else {
                $result['properties'] = [];

                foreach ($schema['properties'] as $name => $property) {
                    $result['properties'][$name] = $this->convertSchema($property);
                }
            }
        }

        if (isset($schema['required'])) {
            $result['required'] = $schema['required'];
        }

        if (isset($schema['items'])) {
            $result['items'] = $this->convertSchema(
                    $schema['items']
            );
        }

        if (isset($schema['enum'])) {
            $result['enum'] = $schema['enum'];
        }

        return $result;
    }

    /**
     * Wykonuje funkcję wybraną przez Gemini.
     */
    public function executeFunction(array $functionCall): array {
        $name = $functionCall['name'] ?? null;
        $arguments = $functionCall['args'] ?? [];

        if (!is_string($name) || $name === '') {
            throw new RuntimeException('Invalid Gemini function name.');
        }

        // Gemini może zwrócić argumenty jako obiekt lub tablicę.
        if (is_object($arguments)) {
            $arguments = (array) $arguments;
        }

        if (!is_array($arguments)) {
            throw new RuntimeException('Invalid function arguments.');
        }

        $result = $this->client->callTool($name, $arguments);

        return [
            'name' => $name,
            'response' => $result,
        ];
    }
}
