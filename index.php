<?php
session_start();
define('PUBLIC_PATH', __DIR__);
define('LOG_PATH', __DIR__ . DIRECTORY_SEPARATOR . 'logs' . DIRECTORY_SEPARATOR);
error_reporting(E_ALL ^ E_DEPRECATED);
ini_set('display_errors', '0'); 
ini_set('log_errors', 1);
ini_set("error_log", LOG_PATH . 'main.log');
require_once 'library/Render.php';
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


