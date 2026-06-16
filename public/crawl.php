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

function statusCssClass(string $status): string
{
	return match ($status) {
		'completed' => 'statusSuccess',
		'running' => 'statusInfo',
		'failed', 'rejected' => 'statusDanger',
		default => 'statusNeutral',
	};
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
	<link rel="icon" type="image/x-icon" href="/assets/images/favicon/favicon.ico">
	<link rel="stylesheet" type="text/css" href="/assets/css/style.css?v=admin-20260616">
</head>
<body class="adminBody">
	<div class="adminApp">
		<header class="adminTopbar">
			<a class="adminBrand" href="index.php">
				<span class="adminBrandMark">D</span>
				<span>Doogle Admin</span>
			</a>
			<nav class="adminNav" aria-label="Admin navigation">
				<a href="index.php">Search</a>
				<a class="active" href="crawl.php">Crawl</a>
				<a href="ranking.php">Ranking</a>
				<a href="logout.php">Logout</a>
			</nav>
		</header>

		<main class="adminShell">
			<section class="adminPageHeader">
				<div>
					<p class="adminEyebrow">Operations</p>
					<h1>Crawler</h1>
				</div>
			</section>

		<?php if ($error !== ''): ?>
			<p class="adminAlert adminAlertDanger"><?php echo h($error); ?></p>
		<?php endif; ?>

		<?php if ($historyError !== ''): ?>
			<p class="adminAlert adminAlertWarning"><?php echo h($historyError); ?></p>
		<?php endif; ?>

			<section class="adminPanel">
				<div class="adminPanelHeader">
					<div>
						<h2>New crawl</h2>
						<p>Submit a URL for the authenticated crawler.</p>
					</div>
				</div>
				<form class="adminForm adminCrawlForm" action="crawl.php" method="post" accept-charset="utf-8">
					<input type="hidden" name="csrf_token" value="<?php echo h($csrfToken); ?>">
					<label for="crawl-input">URL</label>
					<div class="adminInputRow">
						<input type="url" name="url" required="required" id="crawl-input" value="<?php echo h($submittedUrl); ?>" placeholder="https://example.com">
						<button class="adminPrimaryButton" type="submit">Crawl</button>
					</div>
				</form>
			</section>

		<?php if ($result !== null): ?>
			<section class="adminPanel">
				<div class="adminPanelHeader">
					<div>
						<h2>Last crawl</h2>
						<p><?php echo h($submittedUrl); ?></p>
					</div>
					<span class="statusBadge <?php echo $result->successful ? 'statusSuccess' : 'statusDanger'; ?>">
						<?php echo $result->successful ? 'Completed' : 'Failed'; ?>
					</span>
				</div>

			<?php if ($result->errors !== []): ?>
				<div class="adminAlert adminAlertDanger">
					<?php foreach ($result->errors as $resultError): ?>
						<p><?php echo h($resultError); ?></p>
					<?php endforeach; ?>
				</div>
			<?php endif; ?>

			<div class="adminStatGrid">
				<div class="adminStat">
					<span>Pages discovered</span>
					<strong><?php echo h((string) $result->pagesDiscovered); ?></strong>
				</div>
				<div class="adminStat">
					<span>Pages indexed</span>
					<strong><?php echo h((string) $result->pagesIndexed); ?></strong>
				</div>
				<div class="adminStat">
					<span>Images indexed</span>
					<strong><?php echo h((string) $result->imagesIndexed); ?></strong>
				</div>
				<div class="adminStat">
					<span>Videos indexed</span>
					<strong><?php echo h((string) $result->videosIndexed); ?></strong>
				</div>
				<div class="adminStat">
					<span>URLs rejected</span>
					<strong><?php echo h((string) $result->urlsRejected); ?></strong>
				</div>
			</div>

			<?php $output = crawlOutputText($result->output); ?>
			<?php if ($output !== ''): ?>
				<details class="adminDisclosure">
					<summary>Crawl output</summary>
					<pre><?php echo h($output); ?></pre>
				</details>
			<?php endif; ?>
			</section>
		<?php endif; ?>

			<section class="adminPanel">
				<div class="adminPanelHeader">
					<div>
						<h2>Crawl history</h2>
						<p>Recent web and CLI crawl jobs.</p>
					</div>
				</div>

			<?php if ($crawlHistory === []): ?>
				<p class="adminEmptyState">No crawls recorded yet.</p>
			<?php else: ?>
				<div class="adminTableWrap">
					<table class="adminTable">
						<thead>
							<tr>
								<th>Status</th>
								<th>URL</th>
								<th>Indexed</th>
								<th>Rejected</th>
								<th>Updated</th>
							</tr>
						</thead>
						<tbody>
							<?php foreach ($crawlHistory as $job): ?>
								<tr>
									<td>
										<span class="statusBadge <?php echo h(statusCssClass($job->status)); ?>">
											<?php echo h(statusLabel($job->status)); ?>
										</span>
									</td>
									<td>
										<span class="adminTableTitle"><?php echo h($job->startUrl); ?></span>
										<?php if ($job->errorMessage !== null && $job->errorMessage !== ''): ?>
											<span class="adminTableMeta"><?php echo h($job->errorMessage); ?></span>
										<?php endif; ?>
									</td>
									<td>
										<span class="adminTableMeta">
											<?php echo h((string) $job->pagesIndexed); ?> pages,
											<?php echo h((string) $job->imagesIndexed); ?> images,
											<?php echo h((string) $job->videosIndexed); ?> videos
										</span>
									</td>
									<td><?php echo h((string) $job->urlsRejected); ?></td>
									<td><?php echo h($job->updatedAt); ?></td>
								</tr>
							<?php endforeach; ?>
						</tbody>
					</table>
				</div>
			<?php endif; ?>
			</section>
		</main>
	</div>
</body>
</html>
