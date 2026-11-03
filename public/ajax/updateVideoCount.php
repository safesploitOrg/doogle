<?php
include(__DIR__ . "/../../config.php");

if(isset($_POST["videoUrl"]))
{
	$videos = new \Doogle\Repository\VideoRepository($con);
	$videos->incrementClicksByUrl($_POST["videoUrl"]);
}
else
	echo "No video URL passed to page"; //DEBUGGING
?>
