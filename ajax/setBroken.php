<?php
include("../config.php");

if(isset($_POST["src"])) 
{
	$query = $con->prepare("UPDATE images SET broken = 1 WHERE imageUrl=:src");
	$query->bindParam(":src", $_POST["src"]);

	$query->execute();
}
else
	echo "No src passed to page"; //DEBUGGING
?>