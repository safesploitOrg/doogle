<?php

declare(strict_types=1);

use Doogle\Auth\SessionAuth;
use Doogle\Repository\RankingSettingsRepository;
use Doogle\Repository\SearchAnalyticsRepository;
use Doogle\Search\RankingSettings;
use Doogle\Search\RankingWeight;
use Doogle\Search\SearchAnalyticsEvent;
use Doogle\Search\SearchAnalyticsTerm;
use Doogle\Search\SearchAnalyticsTypeSummary;
use Doogle\Security\CsrfToken;
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
$csrf = new CsrfToken($_SESSION);
$logger = SecurityEventLogger::fromEnvironment();
$rankingSettingsRepository = new RankingSettingsRepository($con);
$analyticsRepository = new SearchAnalyticsRepository($con);
$rankingSettings = new RankingSettings();
$message = '';
$error = '';
$selectedType = selectedRankingType($rankingSettings);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $postedType = isset($_POST['type']) ? (string) $_POST['type'] : 'sites';
    $selectedType = supportedRankingType($rankingSettings, $postedType);
    $token = isset($_POST['csrf_token']) ? (string) $_POST['csrf_token'] : null;

    if (!$csrf->verify($token)) {
        $error = 'Invalid request token.';
        $logger->log('ranking.csrf_failed', [
            'user_id' => $currentUser?->id,
            'remote_addr' => requestIp(),
        ]);
    } else {
        try {
            $rankingSettingsRepository->saveWeights($selectedType, postedWeights($_POST['weights'] ?? []));
            $csrf->regenerate();
            $message = ucfirst($selectedType) . ' ranking weights saved.';
            $logger->log('ranking.updated', [
                'user_id' => $currentUser?->id,
                'remote_addr' => requestIp(),
                'type' => $selectedType,
            ]);
        } catch (Throwable $throwable) {
            $error = 'Ranking settings could not be saved. Run migration 005.';
            $logger->log('ranking.update_failed', [
                'user_id' => $currentUser?->id,
                'remote_addr' => requestIp(),
                'type' => $selectedType,
            ]);
        }
    }
}

try {
    $rankingSettings = $rankingSettingsRepository->load();
} catch (Throwable $throwable) {
    $error = $error !== '' ? $error : 'Ranking settings unavailable. Run migration 005.';
}

/** @var list<SearchAnalyticsTypeSummary> $typeSummary */
$typeSummary = [];
/** @var list<SearchAnalyticsTerm> $topTerms */
$topTerms = [];
/** @var list<SearchAnalyticsTerm> $zeroResultTerms */
$zeroResultTerms = [];
/** @var list<SearchAnalyticsEvent> $recentSearches */
$recentSearches = [];
$analyticsError = '';

try {
    $typeSummary = $analyticsRepository->typeSummary();
    $topTerms = $analyticsRepository->topTerms(10);
    $zeroResultTerms = $analyticsRepository->zeroResultTerms(10);
    $recentSearches = $analyticsRepository->recent(20);
} catch (Throwable $throwable) {
    $analyticsError = 'Search analytics unavailable. Run migration 005.';
}

$csrfToken = $csrf->token();

function h(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function requestIp(): string
{
    return (string) ($_SERVER['REMOTE_ADDR'] ?? 'unknown');
}

function selectedRankingType(RankingSettings $settings): string
{
    $type = isset($_GET['type']) ? (string) $_GET['type'] : 'sites';

    return supportedRankingType($settings, $type);
}

function supportedRankingType(RankingSettings $settings, string $type): string
{
    return in_array($type, $settings->supportedTypes(), true) ? $type : 'sites';
}

/**
 * @param mixed $weights
 *
 * @return array<string, int>
 */
function postedWeights(mixed $weights): array
{
    if (!is_array($weights)) {
        return [];
    }

    $postedWeights = [];

    foreach ($weights as $key => $value) {
        if (!is_string($key) || !is_scalar($value)) {
            continue;
        }

        $postedWeights[$key] = (int) $value;
    }

    return $postedWeights;
}

/**
 * @param list<SearchAnalyticsTerm> $terms
 */
function renderTermTable(array $terms): string
{
    if ($terms === []) {
        return '<p>No searches recorded yet.</p>';
    }

    $html = '<table class="adminTable"><thead><tr>'
        . '<th>Term</th><th>Type</th><th>Searches</th><th>Average results</th><th>Last searched</th>'
        . '</tr></thead><tbody>';

    foreach ($terms as $term) {
        $html .= '<tr>'
            . '<td>' . h($term->term) . '</td>'
            . '<td>' . h($term->type) . '</td>'
            . '<td>' . h((string) $term->searches) . '</td>'
            . '<td>' . h((string) $term->averageResultCount) . '</td>'
            . '<td>' . h($term->lastSearchedAt) . '</td>'
            . '</tr>';
    }

    return $html . '</tbody></table>';
}

/**
 * @param list<SearchAnalyticsTypeSummary> $summaries
 */
function renderTypeSummaryTable(array $summaries): string
{
    if ($summaries === []) {
        return '<p>No search vertical analytics recorded yet.</p>';
    }

    $html = '<table class="adminTable"><thead><tr>'
        . '<th>Type</th><th>Searches</th><th>Zero-result searches</th><th>Average results</th>'
        . '</tr></thead><tbody>';

    foreach ($summaries as $summary) {
        $html .= '<tr>'
            . '<td>' . h($summary->type) . '</td>'
            . '<td>' . h((string) $summary->searches) . '</td>'
            . '<td>' . h((string) $summary->zeroResultSearches) . '</td>'
            . '<td>' . h((string) $summary->averageResultCount) . '</td>'
            . '</tr>';
    }

    return $html . '</tbody></table>';
}

/**
 * @param list<SearchAnalyticsEvent> $events
 */
function renderRecentSearchTable(array $events): string
{
    if ($events === []) {
        return '<p>No recent searches recorded yet.</p>';
    }

    $html = '<table class="adminTable"><thead><tr>'
        . '<th>When</th><th>Term</th><th>Type</th><th>Results</th><th>Page</th>'
        . '</tr></thead><tbody>';

    foreach ($events as $event) {
        $html .= '<tr>'
            . '<td>' . h($event->createdAt) . '</td>'
            . '<td>' . h($event->term) . '</td>'
            . '<td>' . h($event->type) . '</td>'
            . '<td>' . h((string) $event->resultCount) . '</td>'
            . '<td>' . h((string) $event->page) . '</td>'
            . '</tr>';
    }

    return $html . '</tbody></table>';
}

/**
 * @param list<RankingWeight> $weights
 */
function renderWeightInputs(array $weights): string
{
    $html = '<table class="adminTable rankingWeights"><thead><tr>'
        . '<th>Signal</th><th>Weight</th><th>Default</th>'
        . '</tr></thead><tbody>';

    foreach ($weights as $weight) {
        $html .= '<tr>'
            . '<td>' . h($weight->label) . '</td>'
            . '<td><input type="number" min="0" max="1000" step="1" name="weights[' . h($weight->key) . ']"'
            . ' value="' . h((string) $weight->value) . '"></td>'
            . '<td>' . h((string) $weight->defaultValue) . '</td>'
            . '</tr>';
    }

    return $html . '</tbody></table>';
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Doogle Ranking</title>
    <meta charset="utf-8">
    <meta name="description" content="Search the web for sites, images, and videos.">
    <meta name="keywords" content="Search engine, doogle, websites">
    <meta name="author" content="Zepher Ashe">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" type="image/x-icon" href="assets/images/favicon/favicon.ico">
    <link rel="stylesheet" type="text/css" href="assets/css/style.css">
</head>
<body>
    <div class="headerContent adminHeader">
        <div class="logoContainer">
            <a href="index.php">Homepage</a>
        </div>
        <nav class="adminNav">
            <a href="crawl.php">Crawl</a>
            <a href="ranking.php">Ranking</a>
            <a href="logout.php">Logout</a>
        </nav>
    </div>

    <div class="mainResultsSection adminSection">
        <?php if ($message !== ''): ?>
            <p class="resultsCount"><?php echo h($message); ?></p>
        <?php endif; ?>

        <?php if ($error !== ''): ?>
            <p class="resultsCount"><?php echo h($error); ?></p>
        <?php endif; ?>

        <?php if ($analyticsError !== ''): ?>
            <p class="resultsCount"><?php echo h($analyticsError); ?></p>
        <?php endif; ?>

        <div class="siteResults">
            <h2>Ranking controls</h2>
            <div class="rankingTabs">
                <?php foreach ($rankingSettings->supportedTypes() as $type): ?>
                    <a class="<?php echo $type === $selectedType ? 'active' : ''; ?>"
                       href="ranking.php?type=<?php echo h($type); ?>">
                        <?php echo h(ucfirst($type)); ?>
                    </a>
                <?php endforeach; ?>
            </div>

            <form action="ranking.php" method="post" class="rankingForm">
                <input type="hidden" name="csrf_token" value="<?php echo h($csrfToken); ?>">
                <input type="hidden" name="type" value="<?php echo h($selectedType); ?>">
                <?php echo renderWeightInputs($rankingSettings->weightDefinitionsFor($selectedType)); ?>
                <button type="submit">Save weights</button>
            </form>

            <p>
                Click boost: min(clicks, <?php echo h((string) $rankingSettings->clickBoostCap()); ?>)
                * <?php echo h((string) $rankingSettings->clickBoostWeight()); ?>
                (max <?php echo h((string) $rankingSettings->clickBoostMaximum()); ?>).
                Full-text boost: min(score * <?php echo h((string) $rankingSettings->fullTextWeight()); ?>,
                <?php echo h((string) $rankingSettings->fullTextCap()); ?>).
            </p>
        </div>

        <div class="siteResults">
            <h2>Search analytics</h2>
            <h3>By vertical</h3>
            <?php echo renderTypeSummaryTable($typeSummary); ?>

            <h3>Top terms</h3>
            <?php echo renderTermTable($topTerms); ?>

            <h3>Zero-result terms</h3>
            <?php echo renderTermTable($zeroResultTerms); ?>

            <h3>Recent searches</h3>
            <?php echo renderRecentSearchTable($recentSearches); ?>
        </div>
    </div>
</body>
</html>
