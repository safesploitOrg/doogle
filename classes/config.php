<?php
ob_start();

$dbname = "doogle";
$dbhost = "mysql_db";
$dbuser = "doogle";
$dbpass = "PASSWORD_HERE";
$debuggingp = false;

try 
{
	$con = new PDO("mysql:dbname=$dbname;host=$dbhost", "$dbuser", "$dbpass");
	$con->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_WARNING);
}
catch(PDOExeption $e) 
{
	if($debuggingp){
		echo "Connection failed: " . $e->getMessage();
	} 
	else {
		exit;
	}
}
?>
