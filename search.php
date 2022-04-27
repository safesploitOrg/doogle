<?php
include("config.php");
include("classes/SiteResultsProvider.php");
include("classes/ImageResultsProvider.php");

if(isset($_GET['term']))
	$term = $_GET['term'];
else
	exit("You must enter a search term!");

$type = isset($_GET["type"]) ? $_GET["type"] : "sites";
$page = isset($_GET["page"]) ? $_GET["page"] : 1;
?>

<!DOCTYPE html>
<html>
<head>
	<title><?php if(isset($term) && $term != '') echo($term . ' | '); ?>Doogle Search</title>

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
							<input type="hidden" name="type" value="<?php echo $type; ?>">
							<input class="searchBox" type="text" name="term" value="<?php echo $term; ?>" autocomplete="off">
							<button class="searchButton">
								<img src="assets/images/icons/search.png">
							</button>
						</div>
					</form>
				</div>
			</div>

			<div class="tabsContainer">
				<ul class="tabList">
					<li class="<?php echo $type == 'sites' ? 'active' : '' ?>">
						<a href='<?php echo "search.php?term=$term&type=sites"; ?>'>
							Sites
						</a>
					</li>
					<li class="<?php echo $type == 'images' ? 'active' : '' ?>">
						<a href='<?php echo "search.php?term=$term&type=images"; ?>'>
							Images
						</a>
					</li>
				</ul>
			</div>
		</div>

		<div class="mainResultsSection">
			<?php
			if($type == "sites") 
			{
				$resultsProvider = new SiteResultsProvider($con);
				$pageSize = 20;
			}
			else if($type == "images")
			{
				$resultsProvider = new ImageResultsProvider($con);
				$pageSize = 30;
			}

			$numResults = $resultsProvider->getNumResults($term);

			echo "<p class='resultsCount'>$numResults results found</p>";
			echo $resultsProvider->getResultsHtml($page, $pageSize, $term);
			?>
		</div>

		<div class="paginationContainer">
			<div class="pageButtons">
				<div class="pageNumberContainer">
					<img src="assets/images/pageStart.png">
				</div>

				<?php
				$pagesToShow = 10;
				$numPages = ceil($numResults / $pageSize);
				$pagesLeft = min($pagesToShow, $numPages);

				$currentPage = $page - floor($pagesToShow / 2);

				if($currentPage < 1)
					$currentPage = 1;

				if($currentPage + $pagesLeft > $numPages + 1)
					$currentPage = $numPages + 1 - $pagesLeft;

				while($pagesLeft != 0 && $currentPage <= $numPages) 
				{
					if($currentPage == $page) 
					{
						echo "<div class='pageNumberContainer'>
								<img src='assets/images/pageSelected.png'>
								<span class='pageNumber'>$currentPage</span>
							</div>";
					}
					else 
					{
						echo "<div class='pageNumberContainer'>
								<a href='search.php?term=$term&type=$type&page=$currentPage'>
									<img src='assets/images/page.png'>
									<span class='pageNumber'>$currentPage</span>
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