<?php
include("../config.php");

if(isset($_POST["src"])) 
{
	$images = new \Doogle\Repository\ImageRepository($con);
	$images->markBroken($_POST["src"]);
}
else
	echo "No src passed to page"; //DEBUGGING
?>
