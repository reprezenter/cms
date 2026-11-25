<?php

namespace AIManager\LLM;

use RuntimeException;

class GeminiClient {

    public function __construct(
            private string $apiKey,
            private string $baseUrl = 'https://generativelanguage.googleapis.com/v1beta',
            private int $timeout = 300
    ) {
        if ($this->apiKey === '') {
            throw new RuntimeException('Gemini API key is missing.');
        }
    }

    /**
     * Dotychczasowe generowanie tekstu.
     * Zachowujemy kompatybilność z obecnym Workerem.
     */
    public function generate(
            string $model,
            string $systemPrompt,
            string $userPrompt,
            array $options = []
    ): array {
        $response = $this->sendRequest(
                $model,
                $systemPrompt,
                [
                    [
                        'role' => 'user',
                        'parts' => [
                            ['text' => $userPrompt],
                        ],
                    ],
                ],
                [],
                $options
        );

        $text = $this->extractText($response['parts']);

        if ($text === '') {
            throw new RuntimeException(
                            'Gemini returned no text: ' . ($response['finish_reason'] ?? 'Empty response')
                    );
        }

        return [
            'text' => $text,
            'model' => $response['model'],
            'usage' => $response['usage'],
            'finish_reason' => $response['finish_reason'],
        ];
    }

    /**
     * Generowanie z obsługą Function Calling.
     *
     * $contents zawiera całą historię konwersacji Gemini.
     * $functionDeclarations zawiera narzędzia przekazane do modelu.
     */
    public function generateWithTools(
            string $model,
            string $systemPrompt,
            array $contents,
            array $functionDeclarations,
            array $options = []
    ): array {
        $response = $this->sendRequest(
                $model,
                $systemPrompt,
                $contents,
                $functionDeclarations,
                $options
        );

        $parts = $response['parts'];
        $functionCalls = [];

        foreach ($parts as $part) {
            if (isset($part['functionCall'])) {
                $functionCalls[] = $part['functionCall'];
            }
        }

        return [
            'text' => $this->extractText($parts),
            'parts' => $parts,
            'content' => $response['content'],
            'function_calls' => $functionCalls,
            'model' => $response['model'],
            'usage' => $response['usage'],
            'finish_reason' => $response['finish_reason'],
        ];
    }

    /**
     * Wspólna komunikacja z Gemini API.
     */
    private function sendRequest(
            string $model,
            string $systemPrompt,
            array $contents,
            array $functionDeclarations = [],
            array $options = []
    ): array {
        $url = rtrim($this->baseUrl, '/')
                . '/models/'
                . rawurlencode($model)
                . ':generateContent';

        $payload = [
            'systemInstruction' => [
                'parts' => [
                    ['text' => $systemPrompt],
                ],
            ],
            'contents' => $this->normalizeContents($contents),
            'generationConfig' => [
                'temperature' => (float) ($options['temperature'] ?? 0.7),
                'maxOutputTokens' => (int) ($options['max_output_tokens'] ?? 4096),
            ],
        ];

        if ($functionDeclarations !== []) {
            $payload['tools'] = [
                [
                    'functionDeclarations' => $functionDeclarations,
                ],
            ];

            $payload['toolConfig'] = [
                'functionCallingConfig' => [
                    'mode' => $options['function_calling_mode'] ?? 'AUTO',
                ],
            ];
        }

        $jsonPayload = json_encode(
                $payload,
                JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE
        );

        $ch = curl_init($url);

        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'x-goog-api-key: ' . $this->apiKey,
            ],
            CURLOPT_POSTFIELDS => $jsonPayload,
            CURLOPT_CONNECTTIMEOUT => 15,
            CURLOPT_TIMEOUT => $this->timeout,
        ]);

        $response = curl_exec($ch);
        $curlError = curl_error($ch);
        $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);

        curl_close($ch);

        if ($response === false) {
            throw new RuntimeException(
                            'Gemini connection error: ' . $curlError
                    );
        }

        $data = json_decode(
                $response,
                true,
                512,
                JSON_THROW_ON_ERROR
        );

        if ($httpCode < 200 || $httpCode >= 300) {
            $message = $data['error']['message'] ?? 'Unknown API error';

            throw new RuntimeException(
                            "Gemini API HTTP {$httpCode}: {$message}"
                    );
        }

        $candidate = $data['candidates'][0] ?? null;

        if (!is_array($candidate)) {
            $reason = $data['promptFeedback']['blockReason'] ?? 'No response candidate';

            throw new RuntimeException(
                            'Gemini returned no candidate: ' . $reason
                    );
        }

        $content = $candidate['content'] ?? [];
        $parts = $content['parts'] ?? [];

        return [
            'content' => $content,
            'parts' => $parts,
            'model' => $data['modelVersion'] ?? $model,
            'usage' => $data['usageMetadata'] ?? [],
            'finish_reason' => $candidate['finishReason'] ?? null,
        ];
    }

    /**
     * Przywraca puste obiekty JSON w historii Gemini.
     */
    private function normalizeContents(array $contents): array {
        foreach ($contents as &$content) {
            if (!isset($content['parts']) || !is_array($content['parts'])) {
                continue;
            }

            foreach ($content['parts'] as &$part) {
                if (
                        isset($part['functionCall']) && is_array($part['functionCall']) && isset($part['functionCall']['args']) && $part['functionCall']['args'] === []
                ) {
                    $part['functionCall']['args'] = new \stdClass();
                }
            }

            unset($part);
        }

        unset($content);

        return $contents;
    }

    /**
     * Wyciąga tekst z odpowiedzi, pomijając wywołania funkcji.
     */
    private function extractText(array $parts): string {
        $text = '';

        foreach ($parts as $part) {
            if (isset($part['text'])) {
                $text .= $part['text'];
            }
        }

        return $text;
    }
}
