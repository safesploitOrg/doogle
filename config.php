<?php
require_once __DIR__ . '/vendor/autoload.php';

use Doogle\Database\ConnectionFactory;

ob_start();

try {
	$connectionFactory = ConnectionFactory::fromProjectRoot(__DIR__);
	$con = $connectionFactory->create();
} catch (PDOException $e) {
	echo "Connection failed: " . $e->getMessage();
}
?>
