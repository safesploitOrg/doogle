<?php
declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';
include(__DIR__ . '/../config.php');

$sessionAuth = new \Doogle\Auth\SessionAuth();
$sessionAuth->start();

if (!$sessionAuth->isAdmin()) {
	header('Location: login.php');
	exit;
}

$csrf = new \Doogle\Security\CsrfToken($_SESSION);
$policy = \Doogle\Security\CrawlerSecurityPolicy::fromEnvironment();
$validator = new \Doogle\Crawl\UrlValidator($policy);
$result = null;
$error = '';
$submittedUrl = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
	$submittedUrl = trim((string) ($_POST['url'] ?? ''));
	$token = isset($_POST['csrf_token']) ? (string) $_POST['csrf_token'] : null;

	if (!$csrf->verify($token)) {
		$error = 'Invalid request token.';
	} else {
		$csrf->regenerate();
		$request = \Doogle\Crawl\CrawlRequest::fromPolicy($submittedUrl, $policy);
		$result = (new \Doogle\Crawl\CrawlService($con, $policy, $validator))->crawl($request);
	}
}

$csrfToken = $csrf->token();

function h(string $value): string
{
	return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function crawlOutputText(string $output): string
{
	return trim(strip_tags(str_ireplace('<br>', "\n", $output)));
}
?>

<!DOCTYPE html>
<html>
<head>
	<title>doogleBot Crawler</title>
	<meta charset="utf-8">
	<meta name="description" content="Search the web for sites and images.">
	<meta name="keywords" content="Search engine, doogle, websites">
	<meta name="author" content="Zepher Ashe">
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<link rel="icon" type="image/x-icon" href="assets/images/favicon/favicon.ico">
	<link rel="stylesheet" type="text/css" href="assets/css/style.css">
</head>
<body>
	<div class="headerContent">
		<div class="logoContainer">
			<a href="index.php">Homepage</a>
		</div>
		<div id="crawl-wrapper">
			<form action="crawl.php" method="post" accept-charset="utf-8">
				<input type="hidden" name="csrf_token" value="<?php echo h($csrfToken); ?>">
				URL: <input type="url" name="url" required="required" id="crawl-input" value="<?php echo h($submittedUrl); ?>">
				<button type="submit">Crawl</button>
			</form>
			<a href="logout.php">Logout</a>
		</div>
	</div>

	<div class="mainResultsSection">
		<?php if ($error !== ''): ?>
			<p class="resultsCount"><?php echo h($error); ?></p>
		<?php endif; ?>

		<?php if ($result !== null): ?>
			<p class="resultsCount">
				<?php echo $result->successful ? 'Crawl completed.' : 'Crawl failed.'; ?>
			</p>

			<?php if ($result->errors !== []): ?>
				<div class="siteResults">
					<?php foreach ($result->errors as $resultError): ?>
						<p><?php echo h($resultError); ?></p>
					<?php endforeach; ?>
				</div>
			<?php endif; ?>

			<div class="siteResults">
				<p>Pages discovered: <?php echo $result->pagesDiscovered; ?></p>
				<p>Pages indexed: <?php echo $result->pagesIndexed; ?></p>
				<p>Images indexed: <?php echo $result->imagesIndexed; ?></p>
				<p>URLs rejected: <?php echo $result->urlsRejected; ?></p>
			</div>

			<?php $output = crawlOutputText($result->output); ?>
			<?php if ($output !== ''): ?>
				<div class="siteResults">
					<pre><?php echo h($output); ?></pre>
				</div>
			<?php endif; ?>
		<?php endif; ?>
	</div>
</body>
</html>
