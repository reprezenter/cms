<?php
$rootFolder = 'cms';
$defaultPageTitle = 'reprezenter CMS';
$defaultPageDescription = 'Szybki i prosty system strony internetowej';
$baseUrl = filter_input(INPUT_SERVER, 'HTTPS', FILTER_SANITIZE_URL) ? 'https://' : 'http://';
$baseUrl .= rtrim(filter_input(INPUT_SERVER, 'SERVER_NAME', FILTER_SANITIZE_URL), '/') . '/' . $rootFolder;

