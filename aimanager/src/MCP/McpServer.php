<?php

namespace AIManager\MCP;

use AIManager\MCP\Cms\CmsFileRepository;
use Throwable;

class McpServer {

    private bool $initialized = false;

    private const PROTOCOL_VERSION = '2025-11-25';

    private array $supportedVersions = [
        '2025-11-25',
        '2025-06-18',
        '2025-03-26'
    ];

    public function __construct(
            private CmsFileRepository $cms
    ) {
        
    }

    /**
     * Główna pętla komunikacji MCP przez STDIO.
     */
    public function run(): void {
        while (($line = fgets(STDIN)) !== false) {
            $line = trim($line);

            if ($line === '') {
                continue;
            }

            try {
                $message = json_decode(
                        $line,
                        true,
                        512,
                        JSON_THROW_ON_ERROR
                );

                if (!is_array($message)) {
                    throw new \RuntimeException('Invalid JSON-RPC message.');
                }

                $response = $this->handle($message);

                if ($response !== null) {
                    $this->send($response);
                }
            } catch (Throwable $e) {
                error_log('MCP error: ' . $e->getMessage());

                $this->send([
                    'jsonrpc' => '2.0',
                    'id' => null,
                    'error' => [
                        'code' => -32700,
                        'message' => 'Parse error'
                    ]
                ]);
            }
        }
    }

    /**
     * Obsługa metod JSON-RPC.
     */
    private function handle(array $message): ?array {
        $method = $message['method'] ?? null;
        $id = $message['id'] ?? null;
        $params = $message['params'] ?? [];

        if (($message['jsonrpc'] ?? '') !== '2.0') {
            return $this->error($id, -32600, 'Invalid JSON-RPC version.');
        }

        if (!is_string($method)) {
            return $this->error($id, -32600, 'Missing method.');
        }

        // Inicjalizacja MCP.
        if ($method === 'initialize') {
            $requestedVersion = $params['protocolVersion'] ?? '';

            $version = in_array(
                            $requestedVersion,
                            $this->supportedVersions,
                            true
                    ) ? $requestedVersion : self::PROTOCOL_VERSION;

            $this->initialized = true;

            return [
                'jsonrpc' => '2.0',
                'id' => $id,
                'result' => [
                    'protocolVersion' => $version,
                    'capabilities' => [
                        'tools' => [
                            'listChanged' => false
                        ]
                    ],
                    'serverInfo' => [
                        'name' => 'reprezenter-cms',
                        'version' => '1.0.0'
                    ],
                    'instructions' =>
                    'CMS file management tools. '
                    . 'Page files are stored in content/*.phtml. '
                    . 'Menu is stored in content/data/menu.json.'
                ]
            ];
        }

        // Powiadomienia nie wymagają odpowiedzi.
        if ($method === 'notifications/initialized') {
            return null;
        }

        if (str_starts_with($method, 'notifications/')) {
            return null;
        }

        if ($method === 'ping') {
            return [
                'jsonrpc' => '2.0',
                'id' => $id,
                'result' => new \stdClass()
            ];
        }

        if (!$this->initialized) {
            return $this->error(
                            $id,
                            -32002,
                            'Server not initialized.'
                    );
        }

        if ($method === 'tools/list') {
            return [
                'jsonrpc' => '2.0',
                'id' => $id,
                'result' => [
                    'tools' => $this->getTools()
                ]
            ];
        }

        if ($method === 'tools/call') {
            return $this->callTool($id, $params);
        }

        return $this->error($id, -32601, 'Method not found.');
    }

    /**
     * Definicje narzędzi udostępnianych AI.
     */
    private function getTools(): array {
        $emptySchema = [
            'type' => 'object',
            'properties' => new \stdClass(),
            'additionalProperties' => false
        ];

        $pageSchema = [
            'type' => 'object',
            'properties' => [
                'slug' => [
                    'type' => 'string',
                    'description' => 'Slug strony, np. oferta'
                ]
            ],
            'required' => ['slug'],
            'additionalProperties' => false
        ];

        $writePageSchema = [
            'type' => 'object',
            'properties' => [
                'slug' => [
                    'type' => 'string',
                    'description' => 'Slug strony, np. oferta'
                ],
                'title' => [
                    'type' => 'string',
                    'description' => 'Tytuł strony'
                ],
                'description' => [
                    'type' => 'string',
                    'description' => 'Opis SEO strony'
                ],
                'html' => [
                    'type' => 'string',
                    'description' => 'Treść strony w formacie PHTML (HTML z opcjonalnym kodem PHP). Parametr title i description służy do ustawienia metadanych SEO, które CMS umieszcza automatycznie na początku pliku. W tym polu umieszczaj właściwą zawartość strony: HTML oraz opcjonalne fragmenty PHP potrzebne do działania szablonu. Nie powtarzaj deklaracji $title i $description. W atrybutach HTML używaj zwykłych cudzysłowów, np. href="/kontakt.html", nigdy href=\"/kontakt.html\".'
                ]
            ],
            'required' => [
                'slug',
                'title',
                'description',
                'html'
            ],
            'additionalProperties' => false
        ];

        $menuItemSchema = [
            'type' => 'object',
            'properties' => [
                'title' => ['type' => 'string'],
                'url' => ['type' => 'string'],
                'class' => ['type' => 'string']
            ],
            'required' => ['title', 'url'],
            'additionalProperties' => false
        ];

        $menuSchema = [
            'type' => 'object',
            'properties' => [
                'main' => [
                    'type' => 'array',
                    'items' => $menuItemSchema
                ],
                'footer' => [
                    'type' => 'array',
                    'items' => $menuItemSchema
                ]
            ],
            'required' => ['main', 'footer'],
            'additionalProperties' => false
        ];

        return [
            [
                'name' => 'cms_list_pages',
                'description' => 'Wyświetla listę wszystkich podstron CMS.',
                'inputSchema' => $emptySchema
            ],
            [
                'name' => 'cms_read_page',
                'description' => 'Odczytuje pełną treść podstrony.',
                'inputSchema' => $pageSchema
            ],
            [
                'name' => 'cms_create_page',
                'description' => 'Tworzy nową podstronę CMS jako plik PHTML. Obsługuje HTML i bezpieczne fragmenty PHP szablonu.',
                'inputSchema' => $writePageSchema
            ],
            [
                'name' => 'cms_update_page',
                'description' => 'Zastępuje treść istniejącej podstrony plikiem PHTML. Obsługuje HTML i fragmenty PHP. Przed zapisem tworzy kopię zapasową.',
                'inputSchema' => $writePageSchema
            ],
            [
                'name' => 'cms_get_menu',
                'description' => 'Odczytuje menu główne i menu stopki.',
                'inputSchema' => $emptySchema
            ],
            [
                'name' => 'cms_update_menu',
                'description' => 'Aktualizuje menu główne i menu stopki. Tworzy kopię zapasową.',
                'inputSchema' => $menuSchema
            ]
        ];
    }

    /**
     * Wywołanie narzędzia.
     */
    private function callTool($id, array $params): array {
        $name = $params['name'] ?? '';
        $args = $params['arguments'] ?? [];

        if (!is_array($args)) {
            $args = [];
        }

        try {
            switch ($name) {
                case 'cms_list_pages':
                    $result = $this->cms->listPages();
                    break;

                case 'cms_read_page':
                    $result = $this->cms->readPage(
                            $this->requiredString($args, 'slug')
                    );
                    break;

                case 'cms_create_page':
                    $result = $this->cms->createPage(
                            $this->requiredString($args, 'slug'),
                            $this->requiredString($args, 'title'),
                            $this->requiredString($args, 'description'),
                            $this->requiredString($args, 'html')
                    );
                    break;

                case 'cms_update_page':
                    $result = $this->cms->updatePage(
                            $this->requiredString($args, 'slug'),
                            $this->requiredString($args, 'title'),
                            $this->requiredString($args, 'description'),
                            $this->requiredString($args, 'html')
                    );
                    break;

                case 'cms_get_menu':
                    $result = $this->cms->getMenu();
                    break;

                case 'cms_update_menu':
                    $result = $this->cms->updateMenu($args);
                    break;

                default:
                    throw new \RuntimeException(
                                    'Unknown CMS tool: ' . $name
                            );
            }

            $text = json_encode(
                    $result,
                    JSON_PRETTY_PRINT |
                    JSON_UNESCAPED_UNICODE |
                    JSON_UNESCAPED_SLASHES |
                    JSON_THROW_ON_ERROR
            );

            return [
                'jsonrpc' => '2.0',
                'id' => $id,
                'result' => [
                    'content' => [
                        [
                            'type' => 'text',
                            'text' => $text
                        ]
                    ],
                    'isError' => false
                ]
            ];
        } catch (Throwable $e) {
            error_log('MCP tool error: ' . $e->getMessage());

            return [
                'jsonrpc' => '2.0',
                'id' => $id,
                'result' => [
                    'content' => [
                        [
                            'type' => 'text',
                            'text' => $e->getMessage()
                        ]
                    ],
                    'isError' => true
                ]
            ];
        }
    }

    private function requiredString(array $args, string $key): string {
        if (!isset($args[$key]) || !is_string($args[$key])) {
            throw new \RuntimeException(
                            'Missing or invalid argument: ' . $key
                    );
        }

        return $args[$key];
    }

    private function error($id, int $code, string $message): array {
        return [
            'jsonrpc' => '2.0',
            'id' => $id,
            'error' => [
                'code' => $code,
                'message' => $message
            ]
        ];
    }

    /**
     * STDOUT jest zarezerwowany wyłącznie dla MCP.
     */
    private function send(array $message): void {
        $json = json_encode(
                $message,
                JSON_UNESCAPED_UNICODE |
                JSON_UNESCAPED_SLASHES |
                JSON_THROW_ON_ERROR
        );

        fwrite(STDOUT, $json . "\n");
        fflush(STDOUT);
    }
}
