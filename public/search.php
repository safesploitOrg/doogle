<?php

declare(strict_types=1);

use Doogle\Repository\ImageRepository;
use Doogle\Repository\SiteRepository;
use Doogle\Search\FieldFormatter;
use Doogle\Search\ImageResult;
use Doogle\Search\ImageSearchService;
use Doogle\Search\Paginator;
use Doogle\Search\SearchResult;
use Doogle\Search\SearchService;

require_once __DIR__ . '/../vendor/autoload.php';
include(__DIR__ . '/../config.php');

if (!isset($_GET['term'])) {
    exit('You must enter a search term!');
}

$term = (string) $_GET['term'];
$type = isset($_GET['type']) ? (string) $_GET['type'] : 'sites';
$type = in_array($type, ['sites', 'images'], true) ? $type : 'sites';
$paginator = new Paginator();
$fieldFormatter = new FieldFormatter();
$page = $paginator->normalizePage(isset($_GET['page']) ? (int) $_GET['page'] : 1);

if ($type === 'images') {
    $pageSize = 30;
    $searchPage = (new ImageSearchService(new ImageRepository($con), $paginator))->search($term, $page, $pageSize);
    $numResults = $searchPage->total;
    $resultsHtml = renderImageResults($searchPage->results);
} else {
    $pageSize = 20;
    $searchPage = (new SearchService(new SiteRepository($con), $paginator))->search($term, $page, $pageSize);
    $numResults = $searchPage->total;
    $resultsHtml = renderSiteResults($searchPage->results, $fieldFormatter);
}

function h(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function queryTerm(string $term): string
{
    return rawurlencode($term);
}

/**
 * @param list<SearchResult> $results
 */
function renderSiteResults(array $results, FieldFormatter $fieldFormatter): string
{
    $html = "<div class='siteResults'>";

    foreach ($results as $result) {
        $id = h((string) $result->id);
        $url = h($result->url);
        $title = h($fieldFormatter->trim($result->title, 55));
        $description = h($fieldFormatter->trim($result->description, 230));

        $html .= "<div class='resultContainer'>
                    <h3 class='title'>
                        <a class='result' href='{$url}' data-linkId='{$id}'>
                            {$title}
                        </a>
                    </h3>
                    <span class='url'>{$url}</span>
                    <span class='description'>{$description}</span>
                </div>";
    }

    return $html . '</div>';
}

/**
 * @param list<ImageResult> $results
 */
function renderImageResults(array $results): string
{
    $html = "<div class='imageResults'>";
    $count = 0;

    foreach ($results as $result) {
        $count++;
        $imageUrl = h($result->imageUrl);
        $siteUrl = h($result->siteUrl);
        $displayText = h($result->displayText());
        $imageUrlJson = jsonForScript($result->imageUrl);

        $html .= "<div class='gridItem image{$count}'>
                    <a href='{$imageUrl}' data-fancybox data-caption='{$displayText}' data-siteurl='{$siteUrl}'>
                        <script>
                        $(document).ready(function() {
                            loadImage({$imageUrlJson}, \"image{$count}\");
                        });
                        </script>

                        <span class='details'>{$displayText}</span>
                    </a>

                </div>";
    }

    return $html . '</div>';
}

function jsonForScript(string $value): string
{
    return (string) json_encode($value, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT);
}
?>

<!DOCTYPE html>
<html>
<head>
    <title><?php if ($term !== '') {
        echo h($term . ' | ');
            } ?>Doogle Search</title>

    <link rel="icon" type="image/x-icon" href="assets/images/favicon/favicon.ico">
    <link rel="shortcut icon" type="image/png" href="assets/images/favicon/favicon-32x32.png">
    <link rel="apple-touch-icon" href="assets/images/favicon/apple-touch-icon.png">
    <link rel="android-chrome-icon" type="image/png" href="assets/images/favicon/android-chrome-512x512.png">

    <meta name="description" content="Search the web for sites and images.">
    <meta name="keywords" content="Search engine, doogle, websites">
    <meta name="author" content="Zepher Ashe">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <!-- <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/fancybox/3.3.5/jquery.fancybox.min.css" /> -->
    <link rel="stylesheet" type="text/css" href="assets/css/fancybox/3.3.5/jquery.fancybox.min.css">
    <link rel="stylesheet" type="text/css" href="assets/css/style.css">

    <script src="assets/js/jquery-3.3.1.min.js"></script>
    <!-- <script src="https://code.jquery.com/jquery-3.3.1.min.js" integrity="sha256-FgpCb/KJQlLNfOu91ta32o/NMZxltwRo8QtmkMRdAu8=" crossorigin="anonymous"></script> -->
</head>
<body>
    <div class="wrapper">
        <div class="header">
            <div class="headerContent">
                <div class="logoContainer">
                    <a href="index.php">
                        <img src="assets/images/doogleLogo.png">
                    </a>
                </div>

                <div class="searchContainer">
                    <form action="search.php" method="GET">
                        <div class="searchBarContainer">
                            <input type="hidden" name="type" value="<?php echo h($type); ?>">
                            <input class="searchBox" type="text" name="term" value="<?php echo h($term); ?>" autocomplete="off">
                            <button class="searchButton">
                                <img src="assets/images/icons/search.png">
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <div class="tabsContainer">
                <ul class="tabList">
                    <li class="<?php echo $type === 'sites' ? 'active' : ''; ?>">
                        <a href='<?php echo 'search.php?term=' . queryTerm($term) . '&type=sites'; ?>'>
                            Sites
                        </a>
                    </li>
                    <li class="<?php echo $type === 'images' ? 'active' : ''; ?>">
                        <a href='<?php echo 'search.php?term=' . queryTerm($term) . '&type=images'; ?>'>
                            Images
                        </a>
                    </li>
                </ul>
            </div>
        </div>

        <div class="mainResultsSection">
            <?php
            echo "<p class='resultsCount'>" . h((string) $numResults) . ' results found</p>';
            echo $resultsHtml;
            ?>
        </div>

        <div class="paginationContainer">
            <div class="pageButtons">
                <div class="pageNumberContainer">
                    <img src="assets/images/pageStart.png">
                </div>

                <?php
                $pagesToShow = 10;
                $numPages = (int) ceil($numResults / $pageSize);
                $pagesLeft = min($pagesToShow, $numPages);

                $currentPage = $page - (int) floor($pagesToShow / 2);

                if ($currentPage < 1) {
                    $currentPage = 1;
                }

                if ($currentPage + $pagesLeft > $numPages + 1) {
                    $currentPage = $numPages + 1 - $pagesLeft;
                }

                while ($pagesLeft !== 0 && $currentPage <= $numPages) {
                    if ($currentPage === $page) {
                        echo "<div class='pageNumberContainer'>
                                <img src='assets/images/pageSelected.png'>
                                <span class='pageNumber'>" . h((string) $currentPage) . "</span>
                            </div>";
                    } else {
                        echo "<div class='pageNumberContainer'>
                                <a href='search.php?term=" . queryTerm($term) . '&type=' . h($type) . '&page=' . h((string) $currentPage) . "'>
                                    <img src='assets/images/page.png'>
                                    <span class='pageNumber'>" . h((string) $currentPage) . "</span>
                                </a>
                        </div>";
                    }

                    $currentPage++;
                    $pagesLeft--;
                }
                ?>

                <div class="pageNumberContainer">
                    <div id="pageEndContainer">
                        <img src="assets/images/pageEnd.png">
                    </div>
                </div>
            </div>
        </div>
    </div>
    <script src="assets/js/fancybox/3.3.5/jquery.fancybox.min.js"></script>
    <script src="assets/js/masonry/4.2.2/masonry.pkgd.min.js"></script>
    <script type="text/javascript" src="assets/js/script.js"></script>
    <!--
    <script src="https://cdnjs.cloudflare.com/ajax/libs/fancybox/3.3.5/jquery.fancybox.min.js"></script>
    <script src="https://unpkg.com/masonry-layout@4/dist/masonry.pkgd.min.js"></script>
    -->
</body>
</html>
