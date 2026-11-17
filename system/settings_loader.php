<?php
/**
 * Load cached settings from system/settings.php, automatically rebuilding from
 * the database when installed if the file is absent, and keep domain synced with .env.
 */
if (file_exists(__DIR__ . '/settings.php')) {
	require(__DIR__ . '/settings.php');
}

if ((!isset($setting) || !is_array($setting) || empty($setting['id'])) && defined('BOOM_INSTALL') && BOOM_INSTALL == 1) {
	mysqli_report(MYSQLI_REPORT_OFF);
	$tmpMysqli = @new mysqli(BOOM_DHOST, BOOM_DUSER, BOOM_DPASS, BOOM_DNAME);
	if ($tmpMysqli && !$tmpMysqli->connect_errno) {
		$q = @$tmpMysqli->query("SELECT * FROM boom_setting WHERE id = 1");
		if ($q && $q->num_rows > 0) {
			$setting = $q->fetch_assoc();
			if (defined('BOOM_DOMAIN') && BOOM_DOMAIN !== '') {
				$setting['domain'] = rtrim(BOOM_DOMAIN, '/');
			}
			if (is_writable(__DIR__) || (file_exists(__DIR__ . '/settings.php') && is_writable(__DIR__ . '/settings.php'))) {
				$out = "<?php\n";
				foreach ($setting as $key => $value) {
					$out .= '$setting[\'' . $key . '\'] = \'' . addslashes((string) $value) . '\';' . "\n";
				}
				$out .= "?>";
				@file_put_contents(__DIR__ . '/settings.php', $out);
			}
		}
		$tmpMysqli->close();
	}
}

if (!isset($setting) || !is_array($setting)) {
	$setting = [
		'use_rate' => 0,
		'redis_status' => 0,
		'timezone' => 'America/Toronto',
		'language' => 'English',
	];
}

if (defined('BOOM_DOMAIN') && BOOM_DOMAIN !== '') {
	$setting['domain'] = rtrim(BOOM_DOMAIN, '/');
}
?>
