<?php
include(__DIR__ . "/../../config.php");

if(isset($_POST["linkId"])) 
{
	$sites = new \Doogle\Repository\SiteRepository($con);
	$sites->incrementClicks($_POST["linkId"]);
}
else
	echo "No link passed to page"; //DEBUGGING
?>
