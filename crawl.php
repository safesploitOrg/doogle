<?php
require_once __DIR__ . '/vendor/autoload.php';

include("config.php");
include_once("classes/Crawler.php");
include_once("classes/DomDocumentParser.php");

if(isset($_SESSION['loggedin']))
{
	exit("You must be logged in!");
    header("location: login.php");
}
?>

<!DOCTYPE html>
<html>
<head>
	<title>doogleBot Crawler</title>

	<link rel="icon" type="image/x-icon" href="assets/images/favicon/favicon.ico">
	<link rel="shortcut icon" type="image/png" href="assets/images/favicon/favicon-32x32.png">
	<link rel="apple-touch-icon" href="assets/images/favicon/apple-touch-icon.png">
	<link rel="android-chrome-icon" type="image/png" href="assets/images/favicon/android-chrome-512x512.png">

	<meta charset="utf-8">
	<meta name="description" content="Search the web for sites and images.">
	<meta name="keywords" content="Search engine, doogle, websites">
	<meta name="author" content="Zepher Ashe">
	<meta name="viewport" content="width=device-width, initial-scale=1.0">

	<link rel="stylesheet" type="text/css" href="assets/css/style.css">
</head>
<body>
	<div class="headerContent">	
		<div class="logoContainer">
			<a href="index.php">
				Homepage
			</a>
		</div>
		<div id="crawl-wrapper">
			<form action="crawl.php" method="post" accept-charset="utf-8">
				URL: <input type="text" name="url" required="required" id="crawl-input" value="">
				<button type="submit">Crawl</button>
			</form>
		</div>
	</div>
</body>
</html>

<?php
if (isset($_POST['url']))
{
	$crawlerObj = new Crawler($con);
	$startUrl = $_POST['url'];
	$crawlerObj->followLinks($startUrl);
}
?>
