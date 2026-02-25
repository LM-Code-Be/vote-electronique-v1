<?php
declare(strict_types=1);

require_once __DIR__ . '/env.php';
env_load(__DIR__ . '/../.env');

$appRoot = realpath(__DIR__ . '/..');
if ($appRoot === false) {
    $appRoot = dirname(__DIR__);
}
define('APP_ROOT', $appRoot);

$appEnv = env_get('APP_ENV', 'local');
$basePath = rtrim((string)env_get('APP_BASE_PATH', '/vote'), '/');
if ($basePath === '') {
    $basePath = '';
}
define('APP_BASE_PATH', $basePath);

function app_url(string $path): string
{
    $path = '/' . ltrim($path, '/');
    return (APP_BASE_PATH === '' ? '' : APP_BASE_PATH) . $path;
}

$appTimezone = env_get('APP_TIMEZONE', 'Europe/Paris');
date_default_timezone_set($appTimezone);

ini_set('default_charset', 'UTF-8');
if (function_exists('mb_internal_encoding')) {
    mb_internal_encoding('UTF-8');
}
if (PHP_SAPI !== 'cli' && !headers_sent()) {
    header('Content-Type: text/html; charset=UTF-8');
}

$debug = $appEnv !== 'production';
ini_set('display_errors', $debug ? '1' : '0');
ini_set('log_errors', '1');
error_reporting(E_ALL);

if (session_status() !== PHP_SESSION_ACTIVE) {
    $isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || ((string)($_SERVER['SERVER_PORT'] ?? '') === '443');
    $cookieSecure = env_get_bool('SESSION_COOKIE_SECURE', $isHttps);
    $cookieSameSite = env_get('SESSION_COOKIE_SAMESITE', 'Lax') ?: 'Lax';
    session_start([
        'cookie_httponly' => true,
        'cookie_secure' => $cookieSecure,
        'cookie_samesite' => $cookieSameSite,
        'use_strict_mode' => true,
    ]);
}

require_once __DIR__ . '/autoload.php';
