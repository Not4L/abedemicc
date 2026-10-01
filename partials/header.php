<?php
// Pembuka semua halaman setelah login: cek login, <head>, frame, dan sidebar.
// Variabel yang bisa diisi halaman sebelum include: $pageTitle, $tab, $contentClass,
// $showStrip, $showMenu (default true kalau $tab diisi).
// Penutup </section>, </div>, </main>, modal, dan script ada di partials/footer.php.
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../history.php';

$user = require_login();

$pageTitle = $pageTitle ?? 'Abedemic';
$tab = $tab ?? null;
$contentClass = $contentClass ?? '';
$showStrip = $showStrip ?? false;
$showMenu = $showMenu ?? ($tab !== null);

// Dipakai modal Riwayat di footer.php. Halaman yang butuh query sendiri (chat/summary/quiz)
// tetap pakai nilai masing-masing supaya tidak dobel query.
$sidebarHistory = $sidebarHistory ?? [
	'chat' => get_conversations($user['id'], 12),
	'summaries' => get_summaries($user['id'], 8),
	'quizzes' => get_quizzes($user['id'], 8),
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
	<meta charset="UTF-8">
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<title><?= e($pageTitle) ?> | Abedemic</title>
	<link rel="stylesheet" href="<?= e(asset_url('styles.css')) ?>">
	<link rel="stylesheet" href="<?= e(asset_url('app.css')) ?>">
</head>
<body>
	<main class="app">
		<header class="brand">ABEDEMIC</header>
		<?php if ($tab): ?>
			<div class="page-tab"><?= e($tab) ?></div>
		<?php endif; ?>
		<div class="layout">
			<aside class="sidebar">
				<button class="profile-btn" type="button" data-open="profileModal">
					<img class="profile-image" src="img/rimuru-mascot.jpeg" alt="">
					<span class="profile-name">Profile</span>
				</button>
				<button class="side-link history-link" type="button" data-open="historyModal">Riwayat</button>
				<?php if ($showMenu): ?>
					<a class="side-link menu-link" href="index.php">Menu</a>
				<?php endif; ?>
				<button class="side-link" type="button" data-open="settingModal">Setting</button>
				<a class="side-link logout-link" href="logout.php">Logout</a>
				<?php if ($showStrip): ?>
					<div class="mascot-strip" aria-hidden="true">
						<img src="img/dino-mascot.jpeg" alt="">
						<img src="img/elf-mascot.jpeg" alt="">
					</div>
				<?php endif; ?>
			</aside>
			<section class="content <?= e($contentClass) ?>">
