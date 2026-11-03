<?php
include(__DIR__ . "/../../config.php");

if(isset($_POST["imageUrl"])) 
{
	$images = new \Doogle\Repository\ImageRepository($con);
	$images->incrementClicksByUrl($_POST["imageUrl"]);
}
else
	echo "No image URL passed to page"; //DEBUGGING
?>
