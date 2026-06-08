<?php
declare(strict_types=1);

use Doogle\Auth\SessionAuth;
use Doogle\Crawl\CrawlRequest;
use Doogle\Crawl\CrawlService;
use Doogle\Crawl\UrlValidator;
use Doogle\Repository\CrawlJobRepository;
use Doogle\Security\CrawlerSecurityPolicy;
use Doogle\Security\CsrfToken;
use Doogle\Security\RateLimiter;
use Doogle\Security\SecurityEventLogger;

require_once __DIR__ . '/../vendor/autoload.php';
include(__DIR__ . '/../config.php');

$sessionAuth = new SessionAuth();
$sessionAuth->start();

if (!$sessionAuth->isAdmin()) {
	header('Location: login.php');
	exit;
}

$currentUser = $sessionAuth->user();
$logger = SecurityEventLogger::fromEnvironment();
$rateLimiter = RateLimiter::fromEnvironment('crawl');
$csrf = new CsrfToken($_SESSION);
$policy = CrawlerSecurityPolicy::fromEnvironment();
$validator = new UrlValidator($policy);
$crawlJobs = new CrawlJobRepository($con);
$result = null;
$error = '';
$historyError = '';
$submittedUrl = '';
$crawlHistory = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
	$submittedUrl = trim((string) ($_POST['url'] ?? ''));
	$token = isset($_POST['csrf_token']) ? (string) $_POST['csrf_token'] : null;
	$decision = $rateLimiter->attempt('crawl', crawlRateLimitIdentity($currentUser?->id));

	if (!$decision->allowed) {
		http_response_code(429);
		header('Retry-After: ' . $decision->retryAfterSeconds);
		$error = 'Too many crawl requests. Try again shortly.';
		$logger->log('crawl.rate_limited', [
			'user_id' => $currentUser?->id,
			'remote_addr' => requestIp(),
			'retry_after_seconds' => $decision->retryAfterSeconds,
		]);
	} elseif (!$csrf->verify($token)) {
		$error = 'Invalid request token.';
		$logger->log('crawl.csrf_failed', [
			'user_id' => $currentUser?->id,
			'remote_addr' => requestIp(),
		]);
	} else {
		$csrf->regenerate();
		$jobId = null;

		try {
			$jobId = $crawlJobs->create($submittedUrl, $currentUser?->id);
			$crawlJobs->markRunning($jobId);
		} catch (Throwable $throwable) {
			$historyError = 'Crawl history unavailable. Run the crawl_jobs migration.';
			$logger->log('crawl.history_unavailable', [
				'user_id' => $currentUser?->id,
				'remote_addr' => requestIp(),
			]);
		}

		$logger->log('crawl.requested', [
			'user_id' => $currentUser?->id,
			'remote_addr' => requestIp(),
			'url' => $submittedUrl,
		]);
		$request = CrawlRequest::fromPolicy($submittedUrl, $policy);
		$result = (new CrawlService($con, $policy, $validator))->crawl($request);
		$logger->log($result->successful ? 'crawl.completed' : 'crawl.failed', [
			'user_id' => $currentUser?->id,
			'remote_addr' => requestIp(),
			'url' => $submittedUrl,
			'pages_indexed' => $result->pagesIndexed,
			'images_indexed' => $result->imagesIndexed,
			'videos_indexed' => $result->videosIndexed,
			'urls_rejected' => $result->urlsRejected,
		]);

		if ($jobId !== null) {
			try {
				$crawlJobs->markFromResult($jobId, $result);
			} catch (Throwable $throwable) {
				$historyError = 'Crawl history could not be updated.';
				$logger->log('crawl.history_update_failed', [
					'user_id' => $currentUser?->id,
					'remote_addr' => requestIp(),
				]);
			}
		}
	}
}

try {
	$crawlHistory = $crawlJobs->recent(10);
} catch (Throwable $throwable) {
	$historyError = 'Crawl history unavailable. Run the crawl_jobs migration.';
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

function statusLabel(string $status): string
{
	return ucfirst($status);
}

function requestIp(): string
{
	return (string) ($_SERVER['REMOTE_ADDR'] ?? 'unknown');
}

function crawlRateLimitIdentity(?int $userId): string
{
	return $userId !== null ? 'user:' . $userId : 'ip:' . requestIp();
}
?>

<!DOCTYPE html>
<html>
<head>
	<title>doogleBot Crawler</title>
	<meta charset="utf-8">
	<meta name="description" content="Search the web for sites, images, and videos.">
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
			<a href="ranking.php">Ranking</a>
			<a href="logout.php">Logout</a>
		</div>
	</div>

	<div class="mainResultsSection">
		<?php if ($error !== ''): ?>
			<p class="resultsCount"><?php echo h($error); ?></p>
		<?php endif; ?>

		<?php if ($historyError !== ''): ?>
			<p class="resultsCount"><?php echo h($historyError); ?></p>
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
				<p>Videos indexed: <?php echo $result->videosIndexed; ?></p>
				<p>URLs rejected: <?php echo $result->urlsRejected; ?></p>
			</div>

			<?php $output = crawlOutputText($result->output); ?>
			<?php if ($output !== ''): ?>
				<div class="siteResults">
					<pre><?php echo h($output); ?></pre>
				</div>
			<?php endif; ?>
		<?php endif; ?>

		<div class="siteResults">
			<h3>Crawl history</h3>

			<?php if ($crawlHistory === []): ?>
				<p>No crawls recorded yet.</p>
			<?php else: ?>
				<?php foreach ($crawlHistory as $job): ?>
					<div class="resultContainer">
						<h3 class="title"><?php echo h(statusLabel($job->status)); ?>: <?php echo h($job->startUrl); ?></h3>
						<span class="description">
							Pages discovered: <?php echo h((string) $job->pagesDiscovered); ?>,
							pages indexed: <?php echo h((string) $job->pagesIndexed); ?>,
							images indexed: <?php echo h((string) $job->imagesIndexed); ?>,
							videos indexed: <?php echo h((string) $job->videosIndexed); ?>,
							URLs rejected: <?php echo h((string) $job->urlsRejected); ?>
						</span>
						<span class="url"><?php echo h($job->updatedAt); ?></span>
						<?php if ($job->errorMessage !== null && $job->errorMessage !== ''): ?>
							<span class="description"><?php echo h($job->errorMessage); ?></span>
						<?php endif; ?>
					</div>
				<?php endforeach; ?>
			<?php endif; ?>
		</div>
	</div>
</body>
</html>
