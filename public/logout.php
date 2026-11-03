<?php
declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

$sessionAuth = new \Doogle\Auth\SessionAuth();
$sessionAuth->logout();

header('Location: login.php');
exit;
