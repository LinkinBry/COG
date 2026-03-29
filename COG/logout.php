<?php
// logout.php
require_once 'config/session.php';

Session::destroy();
header("Location: index.php");
exit();
?>