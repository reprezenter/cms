<?php

$rootFolder = '';
$defaultPageTitle = 'reprezenter CMS with AI manager';
$defaultPageDescription = 'Szybki i prosty system strony internetowej';
$baseUrl = filter_input(INPUT_SERVER, 'HTTPS', FILTER_SANITIZE_URL) ? 'https://' : 'http://';
$baseUrl .= rtrim(filter_input(INPUT_SERVER, 'SERVER_NAME', FILTER_SANITIZE_URL), '/') . '/' . $rootFolder;
$dbHost = '127.0.0.1';
$dbName = 'aimanager';
$dbCharset = 'utf8mb4';
$dbUser = 'aimanager';
$dbPassword = '';
$sessionMsgTemplate = PUBLIC_PATH . DIRECTORY_SEPARATOR . 'content' . DIRECTORY_SEPARATOR . 'partial' . DIRECTORY_SEPARATOR . 'sessionMsg.phtml';
$authTemplate = PUBLIC_PATH . DIRECTORY_SEPARATOR . 'content' . DIRECTORY_SEPARATOR . 'partial' . DIRECTORY_SEPARATOR . 'auth.phtml';
$authAllowedUrl = array();
$llm = ['gemini' => [
        'api_key' => '',
        'base_url' => 'https://generativelanguage.googleapis.com/v1beta',
        'timeout' => 300,
    ],];

