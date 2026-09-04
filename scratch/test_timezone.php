<?php
require_once __DIR__ . '/../config.php';

date_default_timezone_set('Asia/Kolkata');
$now_str = date('Y-m-d H:i:s');
$ts = strtotime($now_str);
$now = time();
echo "now_str: " . $now_str . "\n";
echo "ts: " . $ts . "\n";
echo "now: " . $now . "\n";
echo "diff: " . ($now - $ts) . " seconds\n";
