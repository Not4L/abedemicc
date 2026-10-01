<?php
// Fungsi baca/tulis riwayat obrolan, ringkasan, dan kuis. Di-include setelah config.php.

/**
 * Pastikan tabel obrolan per-konversasi siap dipakai (dijalankan sekali per request).
 * Kalau user tidak punya hak CREATE/ALTER, hasilnya false dan seluruh fungsi di bawah
 * otomatis jatuh ke mode lama: semua pesan dianggap satu obrolan panjang.
 */
function chat_conversations_ready(): bool
{
	global $pdo;
	static $ready = null;

	if ($ready !== null) {
		return $ready;
	}
	$ready = false;

	try {
		$pdo->exec(
			'CREATE TABLE IF NOT EXISTS chat_conversations (
				id INT UNSIGNED NOT NULL AUTO_INCREMENT,
				user_id INT UNSIGNED NOT NULL,
				title VARCHAR(150) NOT NULL,
				created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
				updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
				PRIMARY KEY (id),
				KEY user_id (user_id),
				CONSTRAINT chat_conversations_ibfk_1 FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE
			) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
		);

		$check = $pdo->query(
			"SELECT COUNT(*) FROM information_schema.COLUMNS
			 WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'chat_messages' AND COLUMN_NAME = 'conversation_id'"
		);

		if ((int) $check->fetchColumn() === 0) {
			$pdo->exec(
				'ALTER TABLE chat_messages
				 ADD COLUMN conversation_id INT UNSIGNED NULL AFTER user_id,
				 ADD KEY chat_messages_conversation (conversation_id)'
			);
		}

		// Pesan lama dipindah ke satu obrolan pertama supaya tidak hilang dari riwayat.
		$legacy = $pdo->query('SELECT DISTINCT user_id FROM chat_messages WHERE conversation_id IS NULL')->fetchAll();
		foreach ($legacy as $row) {
			$insert = $pdo->prepare('INSERT INTO chat_conversations (user_id, title) VALUES (?, ?)');
			$insert->execute([$row['user_id'], 'Obrolan']);
			$link = $pdo->prepare('UPDATE chat_messages SET conversation_id = ? WHERE user_id = ? AND conversation_id IS NULL');
			$link->execute([(int) $pdo->lastInsertId(), $row['user_id']]);
		}

		$ready = true;
	} catch (PDOException $ex) {
		$ready = false;
	}

	return $ready;
}

// Judul obrolan diambil dari pesan pertama siswa, dipotong biar muat di daftar riwayat.
function conversation_title(string $text): string
{
	$text = trim(preg_replace('/\s+/', ' ', $text) ?? '');

	if ($text === '') {
		return 'Obrolan Baru';
	}

	if (mb_strlen($text) > 80) {
		$text = mb_substr($text, 0, 80) . '...';
	}

	return $text;
}

function start_new_conversation(int $userId, string $title = 'Obrolan Baru'): int
{
	global $pdo;

	if (!chat_conversations_ready()) {
		return 0;
	}

	$stmt = $pdo->prepare('INSERT INTO chat_conversations (user_id, title) VALUES (?, ?)');
	$stmt->execute([$userId, $title]);

	return (int) $pdo->lastInsertId();
}

// Obrolan yang terakhir dipakai user. Kalau belum pernah, dibuatkan satu.
function current_conversation_id(int $userId): int
{
	global $pdo;

	if (!chat_conversations_ready()) {
		return 0;
	}

	$stmt = $pdo->prepare('SELECT id FROM chat_conversations WHERE user_id = ? ORDER BY id DESC LIMIT 1');
	$stmt->execute([$userId]);
	$id = $stmt->fetchColumn();

	return $id ? (int) $id : start_new_conversation($userId);
}

// Satu obrolan milik user ini saja (jaga privasi antar akun). Null kalau bukan miliknya.
function find_conversation(int $userId, int $conversationId): ?array
{
	global $pdo;

	if ($conversationId <= 0 || !chat_conversations_ready()) {
		return null;
	}

	$stmt = $pdo->prepare('SELECT id, title, created_at, updated_at FROM chat_conversations WHERE id = ? AND user_id = ?');
	$stmt->execute([$conversationId, $userId]);

	return $stmt->fetch() ?: null;
}

function rename_conversation(int $userId, int $conversationId, string $title): void
{
	global $pdo;

	if (!find_conversation($userId, $conversationId)) {
		return;
	}

	$stmt = $pdo->prepare('UPDATE chat_conversations SET title = ? WHERE id = ? AND user_id = ?');
	$stmt->execute([conversation_title($title), $conversationId, $userId]);
}

// Daftar obrolan untuk modal Riwayat, terbaru di atas.
function get_conversations(int $userId, int $limit = 12): array
{
	global $pdo;

	if (!chat_conversations_ready()) {
		return [];
	}

	$sql = 'SELECT c.id, c.title, c.created_at, c.updated_at,
				   (SELECT COUNT(*) FROM chat_messages m WHERE m.conversation_id = c.id) AS message_count
			FROM chat_conversations c
			WHERE c.user_id = ?
			ORDER BY c.updated_at DESC, c.id DESC
			LIMIT ?';

	$stmt = $pdo->prepare($sql);
	$stmt->bindValue(1, $userId, PDO::PARAM_INT);
	$stmt->bindValue(2, $limit, PDO::PARAM_INT);
	$stmt->execute();

	// Buang obrolan kosong (mis. user baru masuk Riwayat tanpa pernah chat).
	return array_values(array_filter($stmt->fetchAll(), static fn($row) => (int) $row['message_count'] > 0));
}

function save_chat_message(int $userId, string $role, string $message, int $conversationId = 0): void
{
	global $pdo;

	$useColumn = $conversationId > 0 && chat_conversations_ready();

	$stmt = $useColumn
		? $pdo->prepare('INSERT INTO chat_messages (user_id, conversation_id, role, message) VALUES (?, ?, ?, ?)')
		: $pdo->prepare('INSERT INTO chat_messages (user_id, role, message) VALUES (?, ?, ?)');

	$stmt->execute($useColumn ? [$userId, $conversationId, $role, $message] : [$userId, $role, $message]);
}

// Ambil $limit pesan terakhir satu obrolan, urut dari yang paling lama ke paling baru.
// Kalau $conversationId tidak valid -> semua pesan user (mode lama).
function get_conversation_messages(int $userId, int $conversationId, int $limit = 100): array
{
	global $pdo;

	if ($conversationId <= 0 || !find_conversation($userId, $conversationId)) {
		return get_chat_history($userId, $limit);
	}

	$stmt = $pdo->prepare(
		'SELECT role, message, created_at FROM chat_messages
		 WHERE conversation_id = ? ORDER BY id DESC LIMIT ?'
	);
	$stmt->bindValue(1, $conversationId, PDO::PARAM_INT);
	$stmt->bindValue(2, $limit, PDO::PARAM_INT);
	$stmt->execute();

	return array_reverse($stmt->fetchAll());
}

// Ambil $limit pesan terakhir user, urut dari yang paling lama ke paling baru (siap ditampilkan).
function get_chat_history(int $userId, int $limit = 50): array
{
	global $pdo;
	$stmt = $pdo->prepare('SELECT role, message, created_at FROM chat_messages WHERE user_id = ? ORDER BY id DESC LIMIT ?');
	$stmt->bindValue(1, $userId, PDO::PARAM_INT);
	$stmt->bindValue(2, $limit, PDO::PARAM_INT);
	$stmt->execute();

	return array_reverse($stmt->fetchAll());
}

// Gabungkan riwayat chat jadi satu teks, dipakai sebagai "materi" saat Summary/Quiz dibuat dari obrolan.
function chat_history_as_text(int $userId, int $limit = 40, int $conversationId = 0): string
{
	$rows = $conversationId > 0
		? get_conversation_messages($userId, $conversationId, $limit)
		: get_chat_history($userId, $limit);

	if (!$rows) {
		return '';
	}

	$lines = [];
	foreach ($rows as $row) {
		$lines[] = ($row['role'] === 'user' ? 'Siswa' : 'Abe') . ': ' . $row['message'];
	}

	return implode("\n", $lines);
}

function save_summary(int $userId, string $sourceLabel, string $content): int
{
	global $pdo;
	$stmt = $pdo->prepare('INSERT INTO summaries (user_id, source_label, content) VALUES (?, ?, ?)');
	$stmt->execute([$userId, $sourceLabel, $content]);

	return (int) $pdo->lastInsertId();
}

function get_summaries(int $userId, int $limit = 10): array
{
	global $pdo;
	$stmt = $pdo->prepare('SELECT id, source_label, created_at FROM summaries WHERE user_id = ? ORDER BY id DESC LIMIT ?');
	$stmt->bindValue(1, $userId, PDO::PARAM_INT);
	$stmt->bindValue(2, $limit, PDO::PARAM_INT);
	$stmt->execute();

	return $stmt->fetchAll();
}

// Ambil satu ringkasan, hanya kalau benar milik user ini (jaga privasi antar akun).
function get_summary(int $userId, int $id): ?array
{
	global $pdo;
	$stmt = $pdo->prepare('SELECT id, source_label, content, created_at FROM summaries WHERE id = ? AND user_id = ?');
	$stmt->execute([$id, $userId]);

	return $stmt->fetch() ?: null;
}

function save_quiz(int $userId, string $sourceLabel, array $questions): int
{
	global $pdo;
	$stmt = $pdo->prepare('INSERT INTO quizzes (user_id, source_label, questions) VALUES (?, ?, ?)');
	$stmt->execute([$userId, $sourceLabel, json_encode($questions)]);

	return (int) $pdo->lastInsertId();
}

function get_quizzes(int $userId, int $limit = 10): array
{
	global $pdo;
	$stmt = $pdo->prepare('SELECT id, source_label, created_at FROM quizzes WHERE user_id = ? ORDER BY id DESC LIMIT ?');
	$stmt->bindValue(1, $userId, PDO::PARAM_INT);
	$stmt->bindValue(2, $limit, PDO::PARAM_INT);
	$stmt->execute();

	return $stmt->fetchAll();
}

function get_quiz(int $userId, int $id): ?array
{
	global $pdo;
	$stmt = $pdo->prepare('SELECT id, source_label, questions, created_at FROM quizzes WHERE id = ? AND user_id = ?');
	$stmt->execute([$id, $userId]);
	$row = $stmt->fetch();

	if (!$row) {
		return null;
	}

	$row['questions'] = json_decode($row['questions'], true);

	return $row;
}
