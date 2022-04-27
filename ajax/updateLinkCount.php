<?php
include("../config.php");

if(isset($_POST["linkId"])) 
{
	$query = $con->prepare("UPDATE sites SET clicks = clicks + 1 WHERE id=:id");
	$query->bindParam(":id", $_POST["linkId"]);

	$query->execute();
}
else
	echo "No link passed to page"; //DEBUGGING
?>