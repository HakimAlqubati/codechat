<?php
// Dynamic PWA manifest: installed app is a window onto this exact website.
require_once __DIR__ . '/system/config.php';
$siteTitle = !empty($setting['title']) ? trim((string)$setting['title']) : 'Chat';
$domain = !empty($setting['domain']) ? trim((string)$setting['domain']) : '';
if ($domain === '') {
    $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && strtolower((string)$_SERVER['HTTP_X_FORWARDED_PROTO']) === 'https');
    $host = isset($_SERVER['HTTP_HOST']) ? preg_replace('/[^a-zA-Z0-9.\-:]/', '', (string)$_SERVER['HTTP_HOST']) : 'localhost';
    $domain = ($https ? 'https://' : 'http://') . $host;
}
$parts = parse_url($domain);
$scheme = isset($parts['scheme']) && in_array(strtolower($parts['scheme']), ['http','https'], true) ? strtolower($parts['scheme']) : 'https';
$host = isset($parts['host']) ? $parts['host'] : 'localhost';
$port = isset($parts['port']) ? ':' . (int)$parts['port'] : '';
$path = isset($parts['path']) ? '/' . trim($parts['path'], '/') : '';
$base = $path === '' ? '/' : rtrim($path, '/') . '/';
$color = isset($setting['app_color']) && preg_match('/^#[0-9a-fA-F]{6}$/', (string)$setting['app_color']) ? $setting['app_color'] : '#111111';
$short = function_exists('mb_substr') ? mb_substr($siteTitle, 0, 12, 'UTF-8') : substr($siteTitle, 0, 12);
$manifest = [
    'id' => $base,
    'name' => $siteTitle,
    'short_name' => $short,
    'description' => $siteTitle,
    'start_url' => $base . 'index.php',
    'scope' => $base,
    'display' => 'standalone',
    'display_override' => ['standalone'],
    'orientation' => 'any',
    'background_color' => '#111111',
    'theme_color' => $color,
    'prefer_related_applications' => false,
    'icons' => [
        ['src' => $base . 'default_images/pwa/icon_192.png', 'sizes' => '192x192', 'type' => 'image/png', 'purpose' => 'any maskable'],
        ['src' => $base . 'default_images/pwa/icon_512.png', 'sizes' => '512x512', 'type' => 'image/png', 'purpose' => 'any maskable']
    ]
];
header('Content-Type: application/manifest+json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
echo json_encode($manifest, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
