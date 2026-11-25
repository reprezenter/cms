<?php

declare(strict_types=1);

try {
    $pdo = require 'bootstrap.php';

    echo 'AI Manager: OK<br>';
    echo 'PHP: ' . PHP_VERSION . '<br>';
    echo 'Database: ' . htmlspecialchars(
        (string) $pdo->query('SELECT DATABASE()')->fetchColumn()
    );

} catch (Throwable $e) {
    http_response_code(500);
    echo 'Connection error: ' . htmlspecialchars($e->getMessage());
}
