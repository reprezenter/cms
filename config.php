<?php
$rootFolder = 'cms';
$defaultPageTitle = 'reprezenter CMS';
$defaultPageDescription = 'Szybki i prosty system strony internetowej';
$baseUrl = filter_input(INPUT_SERVER, 'HTTPS', FILTER_SANITIZE_URL) ? 'https://' : 'http://';
$baseUrl .= rtrim(filter_input(INPUT_SERVER, 'SERVER_NAME', FILTER_SANITIZE_URL), '/') . '/' . $rootFolder;
$dbHost = 'localhost';
$dbName = 'scms';
$dbUser = 'root';
$dbPassword = '';
$sessionMsgTemplate = PUBLIC_PATH . DIRECTORY_SEPARATOR . 'content' .DIRECTORY_SEPARATOR . 'partial' . DIRECTORY_SEPARATOR . 'sessionMsg.phtml';
$authTemplate = PUBLIC_PATH . DIRECTORY_SEPARATOR . 'content' .DIRECTORY_SEPARATOR . 'partial' . DIRECTORY_SEPARATOR . 'auth.phtml';

