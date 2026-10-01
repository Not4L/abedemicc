<?php
require_once __DIR__ . '/config.php';

$user = require_login();

// Halaman yang boleh dijadikan tujuan kembali (whitelist).
$allowedPages = ['index.php', 'chat.php', 'summary.php', 'quiz.php', 'games.php'];
$back = $_POST['back'] ?? 'index.php';
if (!in_array($back, $allowedPages, true)) {
	$back = 'index.php';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
	$language = $_POST['language'] ?? '';

	if (in_array($language, ['id', 'en'], true)) {
		$stmt = $pdo->prepare('UPDATE users SET language = ? WHERE id = ?');
		$stmt->execute([$language, $user['id']]);
	}
}

header('Location: ' . $back);
exit;
