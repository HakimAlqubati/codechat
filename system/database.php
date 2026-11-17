<?php
// base system prefix
define('BOOM_PREFIX', 'codechat_test_');

// optional base domain
define('BOOM_DOMAIN', 'http://codechat.test/');

// default redis configuration
define('REDIS_IP', '127.0.0.1');
define('REDIS_PORT', 6379);
define('REDIS_TIMEOUT', 0.2);
define('REDIS_PASS', '');

// you can edit these lines to configure new setting for your chat
define('BOOM_DHOST', 'localhost');
define('BOOM_DUSER', 'root');
define('BOOM_DPASS', '');
define('BOOM_DNAME', 'codychat_db');

// base system main path do not modify
define('BOOM_PATH', dirname(__DIR__));

// do not modify those variables
define('BOOM_CRYPT', '6abc1c-e8bd7-813Ucb949bfE-8508728aT770RHN');
define('BOOM_INSTALL', 1);
define('BOOM', 1);
?>