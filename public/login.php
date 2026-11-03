<?php
declare(strict_types=1);

use Doogle\Auth\AuthService;
use Doogle\Auth\AdminLoginEventRepository;
use Doogle\Auth\SessionAuth;
use Doogle\Auth\TotpService;
use Doogle\Auth\UserRepository;
use Doogle\Security\RateLimiter;
use Doogle\Security\SecurityEventLogger;

require_once __DIR__ . '/../vendor/autoload.php';
include(__DIR__ . '/../config.php');

$sessionAuth = new SessionAuth();
$sessionAuth->start();
$logger = SecurityEventLogger::fromEnvironment();
$rateLimiter = RateLimiter::fromEnvironment('login');
$totpRateLimiter = RateLimiter::fromEnvironment('totp');
$users = new UserRepository($con);
$loginEvents = new AdminLoginEventRepository($con);
$totp = new TotpService();

if ($sessionAuth->isAdmin()) {
	header('Location: crawl.php');
	exit;
}

$error = '';
$username = '';
$pendingTotpUser = $sessionAuth->pendingTotpUser();
$showTotpForm = $pendingTotpUser !== null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
	$step = (string) ($_POST['step'] ?? 'password');
	$username = trim((string) ($_POST['username'] ?? ''));
	$password = (string) ($_POST['password'] ?? '');

	if ($step === 'totp') {
		$pendingTotpUser = $sessionAuth->pendingTotpUser();
		$showTotpForm = true;

		if ($pendingTotpUser === null) {
			$error = 'Login expired. Enter your username and password again.';
			$showTotpForm = false;
			$sessionAuth->clearTotpChallenge();
		} else {
			$decision = $totpRateLimiter->attempt('totp', totpRateLimitIdentity($pendingTotpUser->id));

			if (!$decision->allowed) {
				http_response_code(429);
				header('Retry-After: ' . $decision->retryAfterSeconds);
				recordAdminLoginEvent($loginEvents, $pendingTotpUser->username, $pendingTotpUser->id, false, 'totp_rate_limited');
				$error = 'Too many verification attempts. Try again shortly.';
			} else {
				$secret = $users->totpSecret($pendingTotpUser->id);
				$code = (string) ($_POST['totp_code'] ?? '');

				if ($secret !== null && $totp->verify($secret, $code)) {
					$sessionAuth->login($pendingTotpUser);
					recordAdminLoginEvent($loginEvents, $pendingTotpUser->username, $pendingTotpUser->id, true, '');
					$logger->log('auth.login', [
						'user_id' => $pendingTotpUser->id,
						'username' => $pendingTotpUser->username,
						'remote_addr' => requestIp(),
						'totp' => true,
					]);
					header('Location: crawl.php');
					exit;
				}

				recordAdminLoginEvent($loginEvents, $pendingTotpUser->username, $pendingTotpUser->id, false, 'totp');
				$logger->log('auth.totp_failed', [
					'user_id' => $pendingTotpUser->id,
					'username' => $pendingTotpUser->username,
					'remote_addr' => requestIp(),
				]);
				$error = 'Invalid verification code.';
			}
		}
	} else {
		$decision = $rateLimiter->attempt('login', rateLimitIdentity($username));

		if (!$decision->allowed) {
			http_response_code(429);
			header('Retry-After: ' . $decision->retryAfterSeconds);
			recordAdminLoginEvent($loginEvents, $username, null, false, 'password_rate_limited');
			$logger->log('auth.rate_limited', [
				'username' => $username,
				'remote_addr' => requestIp(),
				'retry_after_seconds' => $decision->retryAfterSeconds,
			]);
			$error = 'Too many login attempts. Try again shortly.';
		} else {
			$authService = new AuthService($users);
			$user = $authService->authenticate($username, $password);

			if ($user !== null && $user->role === 'admin') {
				if ($users->isTotpEnabled($user->id)) {
					$sessionAuth->beginTotpChallenge($user);
					$pendingTotpUser = $user;
					$showTotpForm = true;
					$logger->log('auth.totp_required', [
						'user_id' => $user->id,
						'username' => $user->username,
						'remote_addr' => requestIp(),
					]);
				} else {
					$sessionAuth->login($user);
					recordAdminLoginEvent($loginEvents, $user->username, $user->id, true, '');
					$logger->log('auth.login', [
						'user_id' => $user->id,
						'username' => $user->username,
						'remote_addr' => requestIp(),
					]);
					header('Location: crawl.php');
					exit;
				}
			} else {
				recordAdminLoginEvent($loginEvents, $username, null, false, 'password');
				$logger->log('auth.failed', [
					'username' => $username,
					'remote_addr' => requestIp(),
				]);
				$error = 'Invalid username or password.';
			}
		}
	}
}

function h(string $value): string
{
	return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function requestIp(): string
{
	return (string) ($_SERVER['REMOTE_ADDR'] ?? 'unknown');
}

function rateLimitIdentity(string $username): string
{
	return requestIp() . ':' . strtolower($username);
}

function totpRateLimitIdentity(int $userId): string
{
	return requestIp() . ':user:' . $userId;
}

function recordAdminLoginEvent(
	AdminLoginEventRepository $events,
	string $username,
	?int $userId,
	bool $successful,
	string $failureReason,
): void {
	try {
		$events->record($username, $userId, $successful, $failureReason, requestIp());
	} catch (Throwable $throwable) {
		return;
	}
}
?>

<!DOCTYPE html>
<html>
<head>
	<title>Doogle Login</title>
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<link rel="icon" type="image/x-icon" href="assets/images/favicon/favicon.ico">
	<link rel="stylesheet" type="text/css" href="assets/css/style.css">
</head>
<body>
	<div class="wrapper indexPage">
		<div class="mainSection">
			<div class="logoContainer">
				<a href="index.php">
					<img src="assets/images/doogleLogo.png" title="Logo of our site" alt="Site logo">
				</a>
			</div>

			<div class="searchContainer">
				<?php if ($error !== ''): ?>
					<p class="resultsCount"><?php echo h($error); ?></p>
				<?php endif; ?>

				<?php if ($showTotpForm): ?>
					<form action="login.php" method="POST">
						<input type="hidden" name="step" value="totp">
						<input class="searchBox" type="text" name="totp_code" autocomplete="one-time-code" inputmode="numeric" pattern="[0-9]{6}" placeholder="Verification code" required>
						<input class="searchButton" type="submit" value="Verify">
					</form>
				<?php else: ?>
					<form action="login.php" method="POST">
						<input type="hidden" name="step" value="password">
						<input class="searchBox" type="text" name="username" value="<?php echo h($username); ?>" autocomplete="username" placeholder="Username" required>
						<input class="searchBox" type="password" name="password" autocomplete="current-password" placeholder="Password" required>
						<input class="searchButton" type="submit" value="Login">
					</form>
				<?php endif; ?>
			</div>
		</div>
	</div>
</body>
</html>
