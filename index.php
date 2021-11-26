<?php
session_start();
error_reporting(1);
ini_set('display_errors', true);
define('PUBLIC_PATH', __DIR__);
define('LOG_PATH', __DIR__ . DIRECTORY_SEPARATOR . 'logs' . DIRECTORY_SEPARATOR);
require_once 'lib/Render.php';
try {
    $path = filter_input(INPUT_SERVER, 'REQUEST_URI', FILTER_SANITIZE_URL);
    $render = new Render($path);
    echo $render->content();
} catch (\Exception $exc) {
    $logPath = LOG_PATH . 'main.log';
    $currentLog = file_get_contents($logPath);
    $currentLog .= date('Y-m-d H:i:s') . ':' . PHP_EOL . $exc->__toString();
    file_put_contents($logPath, $currentLog);
    http_response_code(200);
    include('lib' . DIRECTORY_SEPARATOR . 'error.php');
    die();
}


