<?php

// Serwer MCP może być uruchamiany wyłącznie z CLI.
if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('Forbidden');
}

ini_set('display_errors', '0');
ini_set('log_errors', '1');

require_once 'aimanager/src/MCP/Cms/CmsFileRepository.php';
require_once 'aimanager/src/MCP/McpServer.php';

use AIManager\MCP\McpServer;
use AIManager\MCP\Cms\CmsFileRepository;

try {
    $repository = new CmsFileRepository();

    $server = new McpServer($repository);

    $server->run();
} catch (Throwable $e) {
    error_log('CMS MCP startup error: ' . $e->getMessage());
    exit(1);
}
