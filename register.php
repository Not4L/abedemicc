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
	$confirm = $_POST['password-confirmation'] ?? '';

	if (!preg_match('/^[A-Za-z0-9_]{3,30}$/', $username)) {
		$errors[] = 'Username 3-30 karakter, hanya huruf, angka, dan underscore.';
	}
	if (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 100) {
		$errors[] = 'Format email tidak valid.';
	}
	if (strlen($password) < 6) {
		$errors[] = 'Password minimal 6 karakter.';
	}
	if ($password !== $confirm) {
		$errors[] = 'Konfirmasi password tidak sama.';
	}

	// Cek username / email yang sudah dipakai.
	if (!$errors) {
		$stmt = $pdo->prepare('SELECT username, email FROM users WHERE username = ? OR email = ?');
		$stmt->execute([$username, $email]);

		foreach ($stmt->fetchAll() as $row) {
			if (strcasecmp($row['username'], $username) === 0) {
				$errors[] = 'Username sudah dipakai.';
			}
			if (strcasecmp($row['email'], $email) === 0) {
				$errors[] = 'Email sudah terdaftar.';
			}
		}
		$errors = array_unique($errors);
	}

	if (!$errors) {
		$stmt = $pdo->prepare('INSERT INTO users (username, email, password_hash) VALUES (?, ?, ?)');
		$stmt->execute([$username, $email, password_hash($password, PASSWORD_DEFAULT)]);

		// Langsung login setelah daftar.
		session_regenerate_id(true);
		$_SESSION['user_id'] = (int) $pdo->lastInsertId();
		header('Location: index.php');
		exit;
	}
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
	<meta charset="UTF-8">
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<title>Create Account | Abedemic</title>
	<link rel="stylesheet" href="<?= e(asset_url('styles.css')) ?>">
	<link rel="stylesheet" href="<?= e(asset_url('app.css')) ?>">
</head>
<body class="login-page register-page">
	<main class="login-shell register-shell">
		<header class="login-brand register-brand">CREATE ACCOUNT</header>
		<section class="login-panel register-panel" aria-labelledby="register-title">
			<h1 id="register-title" class="sr-only">Create an Abedemic account</h1>
			<?php if ($errors): ?>
				<div class="form-error" role="alert">
					<?php foreach ($errors as $error): ?>
						<p><?= e($error) ?></p>
					<?php endforeach; ?>
				</div>
			<?php endif; ?>
			<form class="login-form register-form" action="register.php" method="post">
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
					<input type="password" name="password" placeholder="Password" autocomplete="new-password" required>
				</label>
				<label class="field">
					<span>Confirm Password</span>
					<input type="password" name="password-confirmation" placeholder="Confirm Password" autocomplete="new-password" required>
				</label>
				<button class="login-button" type="submit">Create Account</button>
				<p class="account-prompt">Already an adventurer? <a href="login.php">Log in</a></p>
			</form>
		</section>
	</main>
</body>
</html>