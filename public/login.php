<?php
declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';
include(__DIR__ . '/../config.php');

$sessionAuth = new \Doogle\Auth\SessionAuth();
$sessionAuth->start();

if ($sessionAuth->isAdmin()) {
	header('Location: crawl.php');
	exit;
}

$error = '';
$username = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
	$username = trim((string) ($_POST['username'] ?? ''));
	$password = (string) ($_POST['password'] ?? '');
	$authService = new \Doogle\Auth\AuthService(new \Doogle\Auth\UserRepository($con));
	$user = $authService->authenticate($username, $password);

	if ($user !== null && $user->role === 'admin') {
		$sessionAuth->login($user);
		header('Location: crawl.php');
		exit;
	}

	$error = 'Invalid username or password.';
}

function h(string $value): string
{
	return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
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

				<form action="login.php" method="POST">
					<input class="searchBox" type="text" name="username" value="<?php echo h($username); ?>" autocomplete="username" placeholder="Username" required>
					<input class="searchBox" type="password" name="password" autocomplete="current-password" placeholder="Password" required>
					<input class="searchButton" type="submit" value="Login">
				</form>
			</div>
		</div>
	</div>
</body>
</html>
