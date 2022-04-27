<!DOCTYPE html>
<html>
<head>
	<title>Doogle Web Crawler</title>

	<meta name="description" content="Search the web for sites and images.">
	<meta name="keywords" content="search engine, doogle, websites">
	<meta name="author" content="Zepher Ashe">
	<meta name="viewport" content="width=device-width, initial-scale=1.0">

	<link rel="icon" type="image/x-icon" href="assets/images/favicon/favicon.ico">
	<link rel="shortcut icon" type="image/png" href="assets/images/favicon/favicon-32x32.png">
	<link rel="apple-touch-icon" href="assets/images/favicon/apple-touch-icon.png">
	<link rel="android-chrome-icon" type="image/png" href="assets/images/favicon/android-chrome-512x512.png">

	<link rel="stylesheet" type="text/css" href="assets/css/style.css">
</head>
<body>
    <div class="wrapper indexPage">
        <div class="mainSection">
			<div class="logoContainer">
				<img src="assets/images/doogleLogo.png" title="Logo of our site" alt="Site logo">
			</div>

			<div class="searchContainer">
				<form action="search.php" method="GET">
					<input class="searchBox" type="text" name="term" autocomplete="off">
					<input class="searchButton" type="submit" value="Search">
				</form>
			</div>
		</div>
    </div>
</body>
</html>