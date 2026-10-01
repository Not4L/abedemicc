<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../history.php';
require_once __DIR__ . '/../gemini.php';

header('Content-Type: application/json');
$user = current_user();

if ($user === null) {
	http_response_code(401);
	echo json_encode(['error' => 'Belum login.']);
	exit;
}

$source = $_POST['source'] ?? 'file';

if ($source === 'chat') {
	// Materinya diambil dari satu obrolan Ask Our AI, bukan file upload.
	$requestedId = (int) ($_POST['conversation_id'] ?? 0);
	$conversationId = find_conversation($user['id'], $requestedId) ? $requestedId : 0;
	$material = chat_history_as_text($user['id'], 40, $conversationId);

	if ($material === '') {
		http_response_code(400);
		echo json_encode(['error' => 'Belum ada obrolan untuk diringkas. Coba tanya sesuatu dulu di Ask Our AI.']);
		exit;
	}

	$sourceLabel = $conversationId > 0
		? 'Obrolan: ' . ((find_conversation($user['id'], $conversationId)['title'] ?? '') ?: 'Ask Our AI')
		: 'Obrolan Ask Our AI';
	$part = ['text' => "Transkrip obrolan:\n\n" . $material];
} else {
	if (empty($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
		http_response_code(400);
		echo json_encode(['error' => 'File tidak diterima.']);
		exit;
	}

	$sourceLabel = $_FILES['file']['name'];

	try {
		$part = file_to_gemini_part($_FILES['file']);
	} catch (GeminiException $ex) {
		http_response_code(400);
		echo json_encode(['error' => $ex->getMessage()]);
		exit;
	}
}

$language = $user['language'] === 'en' ? 'English' : 'Bahasa Indonesia';
$systemPrompt = ABE_SYSTEM_PROMPT . "\n\nTugas kamu sekarang: buat ringkasan materi dari yang diberikan. " .
	"Balas HANYA ringkasannya, tanpa basa-basi pembuka/penutup. Jawab dalam $language.";

try {
	$summary = call_gemini($systemPrompt, [$part]);
} catch (GeminiException $ex) {
	http_response_code(502);
	echo json_encode(['error' => $ex->getMessage()]);
	exit;
}

$id = save_summary($user['id'], $sourceLabel, $summary);

echo json_encode(['id' => $id, 'source' => $sourceLabel, 'summary' => $summary]);