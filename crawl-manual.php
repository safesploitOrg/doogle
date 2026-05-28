<?php
require_once __DIR__ . '/vendor/autoload.php';

include("config.php");
include_once("classes/Crawler.php");
include_once("classes/DomDocumentParser.php");

$startUrl = "https://thehackernews.com/";
$crawlerObj = new Crawler($con);
$crawlerObj->followLinks($startUrl);

?>
