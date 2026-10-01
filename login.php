<?php
require_once __DIR__ . '/config.php';

if (current_user()) {
	header('Location: index.php');
	exit;
}

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
	$username = trim($_POST['username'] ?? '');
	$email = trim($_POST['email'] ?? '');
	$password = $_POST['password'] ?? '';

	$stmt = $pdo->prepare('SELECT id, email, password_hash FROM users WHERE username = ?');
	$stmt->execute([$username]);
	$row = $stmt->fetch();

	// Username, email, dan password harus cocok semua.
	if ($row && strcasecmp($row['email'], $email) === 0 && password_verify($password, $row['password_hash'])) {
		session_regenerate_id(true);
		$_SESSION['user_id'] = (int) $row['id'];
		header('Location: index.php');
		exit;
	}

	$errors[] = 'Username, email, atau password salah.';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
	<meta charset="UTF-8">
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<title>Login | Abedemic</title>
	<link rel="stylesheet" href="<?= e(asset_url('styles.css')) ?>">
	<link rel="stylesheet" href="<?= e(asset_url('app.css')) ?>">
</head>
<body class="login-page">
	<main class="login-shell">
		<header class="login-brand">ABEDEMIC</header>
		<section class="login-panel" aria-labelledby="login-title">
			<h1 id="login-title" class="sr-only">Log in to Abedemic</h1>
			<?php if ($errors): ?>
				<div class="form-error" role="alert">
					<?php foreach ($errors as $error): ?>
						<p><?= e($error) ?></p>
					<?php endforeach; ?>
				</div>
			<?php endif; ?>
			<form class="login-form" action="login.php" method="post">
				<label class="field">
					<span>Username</span>
					<input type="text" name="username" placeholder="Username" value="<?= e($_POST['username'] ?? '') ?>" autocomplete="username" required>
				</label>
				<label class="field">
					<span>Email Address</span>
					<input type="email" name="email" placeholder="Email Address" value="<?= e($_POST['email'] ?? '') ?>" autocomplete="email" required>
				</label>
				<label class="field">
					<span>Password</span>
					<input type="password" name="password" placeholder="Password" autocomplete="current-password" required>
				</label>
				<button class="login-button" type="submit">Log In</button>
				<p class="account-prompt">New adventurer? <a href="register.php">Create an account</a></p>
			</form>
		</section>
	</main>
</body>
</html>