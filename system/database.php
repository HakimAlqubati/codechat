<?php
if (!function_exists('boomLoadEnv')) {
	/**
	 * Load key=value pairs from the project root .env file.
	 */
	function boomLoadEnv($forceReload = false)
	{
		static $env = null;
		if ($env !== null && !$forceReload) {
			return $env;
		}
		$env = [];
		$envPath = dirname(__DIR__) . '/.env';
		if (!is_file($envPath) || !is_readable($envPath)) {
			return $env;
		}
		$lines = file($envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
		if ($lines === false) {
			return $env;
		}
		foreach ($lines as $line) {
			$trimmed = trim($line);
			if ($trimmed === '' || $trimmed[0] === '#') {
				continue;
			}
			if (strpos($trimmed, 'export ') === 0) {
				$trimmed = ltrim(substr($trimmed, 7));
			}
			$pos = strpos($trimmed, '=');
			if ($pos === false) {
				continue;
			}
			$key = trim(substr($trimmed, 0, $pos));
			if ($key === '') {
				continue;
			}
			$val = trim(substr($trimmed, $pos + 1));
			$len = strlen($val);
			if ($len >= 2) {
				$first = $val[0];
				$last = $val[$len - 1];
				if (($first === '"' && $last === '"') || ($first === "'" && $last === "'")) {
					$val = substr($val, 1, -1);
					if ($first === '"') {
						$val = str_replace(['\\"', '\\\\'], ['"', '\\'], $val);
					}
				} else {
					$val = preg_replace('/\s+#.*$/', '', $val);
				}
			}
			$env[$key] = $val;
		}
		return $env;
	}
}

if (!function_exists('boomEnv')) {
	/**
	 * Get an environment variable from .env or process environment.
	 */
	function boomEnv($key, $default = '')
	{
		$env = boomLoadEnv();
		if (array_key_exists($key, $env)) {
			return $env[$key];
		}
		$sys = getenv($key);
		if ($sys !== false) {
			return $sys;
		}
		return $default;
	}
}

if (!function_exists('boomWriteEnv')) {
	/**
	 * Update or append key=value pairs in the project root .env file.
	 */
	function boomWriteEnv(array $pairs)
	{
		$envPath = dirname(__DIR__) . '/.env';
		$lines = [];
		if (is_file($envPath) && is_readable($envPath)) {
			$read = file($envPath, FILE_IGNORE_NEW_LINES);
			if ($read !== false) {
				$lines = $read;
			}
		}
		$remaining = $pairs;
		$formatVal = static function ($value) {
			$value = (string) $value;
			if ($value === '') {
				return '';
			}
			if (preg_match('/[\s#"\'\\\\]/', $value)) {
				$escaped = str_replace(['\\', '"'], ['\\\\', '\\"'], $value);
				return '"' . $escaped . '"';
			}
			return $value;
		};
		foreach ($lines as $idx => $line) {
			$trimmed = trim($line);
			if ($trimmed === '' || $trimmed[0] === '#') {
				continue;
			}
			$pos = strpos($trimmed, '=');
			if ($pos === false) {
				continue;
			}
			$key = trim(substr($trimmed, 0, $pos));
			if (strpos($key, 'export ') === 0) {
				$key = trim(substr($key, 7));
			}
			if (array_key_exists($key, $remaining)) {
				$lines[$idx] = $key . '=' . $formatVal($remaining[$key]);
				unset($remaining[$key]);
			}
		}
		foreach ($remaining as $key => $value) {
			$lines[] = $key . '=' . $formatVal($value);
		}
		$ok = @file_put_contents($envPath, implode("\n", $lines) . "\n") !== false;
		if ($ok) {
			boomLoadEnv(true);
		}
		return $ok;
	}
}

if (!defined('BOOM')) {
	// base system prefix
	define('BOOM_PREFIX', (string) boomEnv('BOOM_PREFIX', ''));

	// optional base domain
	define('BOOM_DOMAIN', (string) boomEnv('BOOM_DOMAIN', ''));

	// default redis configuration
	define('REDIS_IP', (string) boomEnv('REDIS_IP', '127.0.0.1'));
	define('REDIS_PORT', (int) boomEnv('REDIS_PORT', 6379));
	define('REDIS_TIMEOUT', (float) boomEnv('REDIS_TIMEOUT', 0.2));
	define('REDIS_PASS', (string) boomEnv('REDIS_PASS', ''));

	// database credentials
	define('BOOM_DHOST', (string) boomEnv('BOOM_DHOST', 'localhost'));
	define('BOOM_DUSER', (string) boomEnv('BOOM_DUSER', ''));
	define('BOOM_DPASS', (string) boomEnv('BOOM_DPASS', ''));
	define('BOOM_DNAME', (string) boomEnv('BOOM_DNAME', ''));

	// base system main path do not modify
	define('BOOM_PATH', dirname(__DIR__));

	// installation & encryption flags
	define('BOOM_CRYPT', (string) boomEnv('BOOM_CRYPT', ''));
	define('BOOM_INSTALL', (int) boomEnv('BOOM_INSTALL', 0));
	define('BOOM', 1);
}
?>
