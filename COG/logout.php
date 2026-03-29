<?php
// logout.php
define('SKIP_TIMEOUT_CHECK', true);
require_once 'config/session.php';

$timeout = isset($_GET['timeout']);
Session::destroy();

if ($timeout) {
    header("Location: index.php?timeout=1");
} else {
    header("Location: index.php");
}
exit();