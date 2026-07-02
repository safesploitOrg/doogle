<?php

declare(strict_types=1);

use Doogle\Auth\AdminLoginEvent;
use Doogle\Auth\AdminLoginEventRepository;
use Doogle\Auth\QrCodeSvg;
use Doogle\Auth\SessionAuth;
use Doogle\Auth\TotpService;
use Doogle\Auth\UserRepository;
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

if ($currentUser === null) {
    header('Location: login.php');
    exit;
}

$csrf = new CsrfToken($_SESSION);
$csrfToken = $csrf->token();
$logger = SecurityEventLogger::fromEnvironment();
$users = new UserRepository($con);
$totp = new TotpService();
$totpRateLimiter = RateLimiter::fromEnvironment('totp');
$qrCode = new QrCodeSvg();
$message = '';
$error = '';
$setupSecret = isset($_SESSION['totp_setup_secret']) ? (string) $_SESSION['totp_setup_secret'] : '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = isset($_POST['csrf_token']) ? (string) $_POST['csrf_token'] : null;
    $action = (string) ($_POST['action'] ?? '');

    if (!$csrf->verify($token)) {
        $error = 'Invalid request token.';
        $logger->log('security.csrf_failed', [
            'user_id' => $currentUser->id,
            'remote_addr' => requestIp(),
        ]);
    } elseif ($action === 'start_totp_setup') {
        $setupSecret = $totp->generateSecret();
        $_SESSION['totp_setup_secret'] = $setupSecret;
    } elseif ($action === 'confirm_totp') {
        $code = (string) ($_POST['totp_code'] ?? '');
        $decision = $totpRateLimiter->attempt('totp_setup', totpSetupRateLimitIdentity($currentUser->id));

        if (!$decision->allowed) {
            http_response_code(429);
            header('Retry-After: ' . $decision->retryAfterSeconds);
            $error = 'Too many verification attempts. Try again shortly.';
            $logger->log('security.totp_setup_rate_limited', [
                'user_id' => $currentUser->id,
                'remote_addr' => requestIp(),
                'retry_after_seconds' => $decision->retryAfterSeconds,
            ]);
        } elseif ($setupSecret === '') {
            $error = 'Start TOTP setup again.';
        } elseif (!$totp->verify($setupSecret, $code)) {
            $error = 'Invalid verification code.';
        } elseif ($users->enableTotp($currentUser->id, $setupSecret)) {
            unset($_SESSION['totp_setup_secret']);
            $setupSecret = '';
            $message = 'TOTP enabled.';
            $logger->log('security.totp_enabled', [
                'user_id' => $currentUser->id,
                'remote_addr' => requestIp(),
            ]);
        } else {
            $error = 'TOTP storage is unavailable. Run migration 006.';
        }
    } elseif ($action === 'disable_totp') {
        if ($users->clearTotp($currentUser->id)) {
            unset($_SESSION['totp_setup_secret']);
            $setupSecret = '';
            $message = 'TOTP disabled.';
            $logger->log('security.totp_disabled', [
                'user_id' => $currentUser->id,
                'remote_addr' => requestIp(),
            ]);
        } else {
            $error = 'TOTP storage is unavailable. Run migration 006.';
        }
    }
}

$totpEnabled = $users->isTotpEnabled($currentUser->id);
$provisioningUri = $setupSecret !== ''
    ? $totp->provisioningUri('Doogle', $currentUser->username, $setupSecret)
    : '';
$qrSvg = '';

try {
    $qrSvg = $provisioningUri !== '' ? $qrCode->render($provisioningUri) : '';
} catch (Throwable $throwable) {
    $error = $error !== '' ? $error : 'QR code could not be generated. Use the secret manually.';
}

/** @var list<AdminLoginEvent> $loginEvents */
$loginEvents = [];
$loginEventsError = '';

try {
    $loginEvents = (new AdminLoginEventRepository($con))->recent(25);
} catch (Throwable $throwable) {
    $loginEventsError = 'Login events unavailable. Run migration 006.';
}

function h(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function requestIp(): string
{
    return (string) ($_SERVER['REMOTE_ADDR'] ?? 'unknown');
}

function totpSetupRateLimitIdentity(int $userId): string
{
    return requestIp() . ':setup:' . $userId;
}

/**
 * @param list<AdminLoginEvent> $events
 */
function renderLoginEvents(array $events): string
{
    if ($events === []) {
        return '<p class="adminEmptyState">No admin login events recorded yet.</p>';
    }

    $html = '<div class="adminTableWrap"><table class="adminTable"><thead><tr>'
        . '<th>When</th><th>Username</th><th>Status</th><th>Reason</th><th>IP address</th>'
        . '</tr></thead><tbody>';

    foreach ($events as $event) {
        $status = $event->successful ? 'Successful' : 'Failed';
        $statusClass = $event->successful ? 'statusSuccess' : 'statusDanger';

        $html .= '<tr>'
            . '<td>' . h($event->createdAt) . '</td>'
            . '<td>' . h($event->username) . '</td>'
            . '<td><span class="statusBadge ' . $statusClass . '">' . $status . '</span></td>'
            . '<td>' . h($event->failureReason) . '</td>'
            . '<td>' . h($event->ipAddress) . '</td>'
            . '</tr>';
    }

    return $html . '</tbody></table></div>';
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Doogle Security</title>
    <meta charset="utf-8">
    <meta name="description" content="Search the web for sites, images, and videos.">
    <meta name="keywords" content="Search engine, doogle, websites">
    <meta name="author" content="Zepher Ashe">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" type="image/x-icon" href="/assets/images/favicon/favicon.ico">
    <link rel="stylesheet" type="text/css" href="/assets/css/style.css?v=admin-20260702">
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
                <a href="crawl.php">Crawl</a>
                <a href="ranking.php">Ranking</a>
                <a class="active" href="security.php">Security</a>
                <a href="logout.php">Logout</a>
            </nav>
        </header>

        <main class="adminShell">
            <section class="adminPageHeader">
                <div>
                    <p class="adminEyebrow">Admin access</p>
                    <h1>Security</h1>
                </div>
            </section>

            <?php if ($message !== ''): ?>
                <p class="adminAlert adminAlertSuccess"><?php echo h($message); ?></p>
            <?php endif; ?>

            <?php if ($error !== ''): ?>
                <p class="adminAlert adminAlertDanger"><?php echo h($error); ?></p>
            <?php endif; ?>

            <?php if ($loginEventsError !== ''): ?>
                <p class="adminAlert adminAlertWarning"><?php echo h($loginEventsError); ?></p>
            <?php endif; ?>

            <section class="adminPanel">
                <div class="adminPanelHeader">
                    <div>
                        <h2>Two-factor authentication</h2>
                        <p>Status: <?php echo $totpEnabled ? 'enabled' : 'disabled'; ?></p>
                    </div>
                    <?php if ($totpEnabled): ?>
                        <span class="statusBadge statusSuccess">Enabled</span>
                    <?php else: ?>
                        <span class="statusBadge statusNeutral">Disabled</span>
                    <?php endif; ?>
                </div>

                <?php if ($totpEnabled): ?>
                    <form class="adminForm" action="security.php" method="post">
                        <input type="hidden" name="csrf_token" value="<?php echo h($csrfToken); ?>">
                        <input type="hidden" name="action" value="disable_totp">
                        <button class="adminSecondaryButton" type="submit">Disable TOTP</button>
                    </form>
                <?php elseif ($setupSecret === ''): ?>
                    <form class="adminForm" action="security.php" method="post">
                        <input type="hidden" name="csrf_token" value="<?php echo h($csrfToken); ?>">
                        <input type="hidden" name="action" value="start_totp_setup">
                        <button class="adminPrimaryButton" type="submit">Set up TOTP</button>
                    </form>
                <?php else: ?>
                    <div class="totpSetupGrid">
                        <?php if ($qrSvg !== ''): ?>
                            <div class="totpQrCode"><?php echo $qrSvg; ?></div>
                        <?php endif; ?>
                        <div>
                            <p class="adminTableMeta">Secret</p>
                            <code class="totpSecret"><?php echo h($setupSecret); ?></code>
                            <form class="adminForm totpConfirmForm" action="security.php" method="post">
                                <input type="hidden" name="csrf_token" value="<?php echo h($csrfToken); ?>">
                                <input type="hidden" name="action" value="confirm_totp">
                                <label for="totp-code">Verification code</label>
                                <div class="adminInputRow">
                                    <input id="totp-code" type="text" name="totp_code" autocomplete="one-time-code" inputmode="numeric" pattern="[0-9]{6}" required>
                                    <button class="adminPrimaryButton" type="submit">Enable</button>
                                </div>
                            </form>
                        </div>
                    </div>
                <?php endif; ?>
            </section>

            <section class="adminPanel">
                <div class="adminPanelHeader">
                    <div>
                        <h2>Admin login events</h2>
                        <p>Successful and failed admin login attempts.</p>
                    </div>
                </div>

                <?php echo renderLoginEvents($loginEvents); ?>
            </section>
        </main>
    </div>
</body>
</html>
