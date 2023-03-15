<?php
include("config.php");
include("classes/Crawler.php");
include("classes/DomDocumentParser.php");

if(isset($_SESSION['loggedin']))
{
	exit("You must be logged in!");
    header("location: login.php");
}

$alreadyCrawled = array();
$crawling = array();
$alreadyFoundImages = array();


function linkExists($url) 
{
	global $con;

	$query = $con->prepare("SELECT * FROM sites WHERE url = :url");

	$query->bindParam(":url", $url);
	$query->execute();

	return $query->rowCount() != 0;
}

function imageExists($src) 
{
	global $con;

	$query = $con->prepare("SELECT * FROM images WHERE imageUrl = :src");

	$query->bindParam(":src", $src);
	$query->execute();

	return $query->rowCount() != 0;
}


function insertLink($url, $title, $description, $keywords)
{
	global $con;

	$query = $con->prepare("INSERT INTO sites(url, title, description, keywords)
							VALUES(:url, :title, :description, :keywords)");

	$query->bindParam(":url", $url);
	$query->bindParam(":title", $title);
	$query->bindParam(":description", $description);
	$query->bindParam(":keywords", $keywords);

	return $query->execute();
}

function insertImage($url, $src, $alt, $title) 
{
	global $con;

	$query = $con->prepare("INSERT INTO images(siteUrl, imageUrl, alt, title)
							VALUES(:siteUrl, :imageUrl, :alt, :title)");

	$query->bindParam(":siteUrl", $url);
	$query->bindParam(":imageUrl", $src);
	$query->bindParam(":alt", $alt);
	$query->bindParam(":title", $title);

	return $query->execute();
}

/* Converts relative link to absolute link */
function createLink($src, $url)
{
	$scheme = parse_url($url)["scheme"]; // http
	$host = parse_url($url)["host"]; // www.safesploit.com
	
	if(substr($src, 0, 2) == "//") 
		$src =  $scheme . ":" . $src;
	else if(substr($src, 0, 1) == "/") 
		$src = $scheme . "://" . $host . $src;
	else if(substr($src, 0, 2) == "./") 
		$src = $scheme . "://" . $host . dirname(parse_url($url)["path"]) . substr($src, 1);
	else if(substr($src, 0, 3) == "../") 
		$src = $scheme . "://" . $host . "/" . $src;
	else if(substr($src, 0, 5) != "https" && substr($src, 0, 4) != "http") 
		$src = $scheme . "://" . $host . "/" . $src;

	return $src;
}

function getDetails($url)
{
	global $alreadyFoundImages;

	$parser = new DomDocumentParser($url);

	$titleArray = $parser->getTitleTags();

	if(sizeof($titleArray) == 0 || $titleArray->item(0) == NULL)
		return;

	//Replace linebreak
	$title = $titleArray->item(0)->nodeValue;
	$title = str_replace("\n", "", $title);

	//Return if no <title>
	if($title == "")
		return;

	$description = "";
	$keywords = "";

	$metasArray = $parser->getMetatags();

	foreach($metasArray as $meta) 
	{
		if($meta->getAttribute("name") == "description")
			$description = $meta->getAttribute("content");

		if($meta->getAttribute("name") == "keywords")
			$keywords = $meta->getAttribute("content");
	}	

	$description = str_replace("\n", "", $description);
	$keywords = str_replace("\n", "", $keywords);

	if(linkExists($url))
		echo "$url already exists<br>";
	else if(insertLink($url, $title, $description, $keywords))
		echo "SUCCESS: $url<br>";
	else
		echo "ERROR: Failed to insert $url<br>";

	$imageArray = $parser->getImages();
	foreach($imageArray as $image) 
	{
		$src = $image->getAttribute("src");
		$alt = $image->getAttribute("alt");
		$title = $image->getAttribute("title");

		if(!$title && !$alt)
			continue;

		$src = createLink($src, $url);

		if(!in_array($src, $alreadyFoundImages)) 
		{
			$alreadyFoundImages[] = $src;

			if(imageExists($src))
				echo "$src already exists<br>";
			else if(insertImage($url, $src, $alt, $title))
				echo "SUCCESS: $src<br>";
			else
				echo "ERROR: Failed to insert $src<br>";
		}

	}

	echo "<b>URL:</b> $url, <b>Title:</b> $title, <b>Description:</b> $description, <b>keywords:</b> $keywords<br>"; //DEBUGGING sites
	echo "<b>src:</b> <a href=$src>$src</a>, <b>alt:</b> $alt, <b>title:</b> $title, <b>url:</b> $url<br>"; //DEBUGGING images
}

function followLinks($url)
{
	global $alreadyCrawled;
	global $crawling;

	$parser = new DomDocumentParser($url);

	$linkList = $parser->getLinks();


	foreach($linkList as $link) 
	{
		$href = $link->getAttribute("href");

		// Filter hrefs
		if(strpos($href, "#") !== false) 
			continue;
		else if(substr($href, 0, 11) == "javascript:") 
			continue;

		$href = createLink($href, $url);

		if(!in_array($href, $alreadyCrawled)) 
		{
			$alreadyCrawled[] = $href;
			$crawling[] = $href;

			getDetails($href);
		}
		//else return; //DEBUGGING

		echo ($href . "<br>"); //DEBUGGING
	}

	array_shift($crawling);

	foreach($crawling as $site)
		followLinks($site);
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
	// $crawlerObj->followLinks($startUrl);
	followLinks($startUrl);
}
?>
