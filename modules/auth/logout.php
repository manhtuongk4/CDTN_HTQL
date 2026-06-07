<?php
session_start();
$_SESSION = array();
session_destroy();
header('Location: /quan_ly_vat_tu/login.php');
exit();
