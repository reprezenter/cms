<?php

define('PUBLIC_PATH', __DIR__ . '/..');
require __DIR__ . '/../config.php';

$dsn = sprintf(
        'mysql:host=%s;dbname=%s;charset=%s',
        $dbHost,
        $dbName,
        $dbCharset
);

$pdo = new PDO($dsn, $dbUser, $dbPassword, [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES => false,
        ]);

return $pdo;
