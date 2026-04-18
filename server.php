<?php

/**
 * Router for PHP's built-in server (`php artisan serve`, Sail).
 *
 * Static files under `public/workflow-editor/` (incl. index.html) make the default
 * Laravel `server.php` treat `/workflow-editor` as a directory and `/workflow-editor/{id}`
 * as PATH_INFO on index.html, breaking Laravel routing. SPA routes must always hit
 * `public/index.php` except real files under `/workflow-editor/assets/`.
 */
$publicPath = getcwd();

$uri = urldecode(
    parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) ?? ''
);

$workflowEditorSpa = $uri === '/workflow-editor'
    || (str_starts_with($uri, '/workflow-editor/') && ! str_starts_with($uri, '/workflow-editor/assets/'));

if ($uri !== '/' && file_exists($publicPath.$uri) && ! $workflowEditorSpa) {
    return false;
}

$formattedDateTime = date('D M j H:i:s Y');

$requestMethod = $_SERVER['REQUEST_METHOD'];
$remoteAddress = $_SERVER['REMOTE_ADDR'].':'.$_SERVER['REMOTE_PORT'];

file_put_contents('php://stdout', "[$formattedDateTime] $remoteAddress [$requestMethod] URI: $uri\n");

require_once $publicPath.'/index.php';
