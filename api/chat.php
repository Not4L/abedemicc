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

$input = json_decode(file_get_contents('php://input'), true);
$message = trim($input['message'] ?? '');

if ($message === '') {
	http_response_code(400);
	echo json_encode(['error' => 'Pertanyaan kosong.']);
	exit;
}
if (mb_strlen($message) > 2000) {
	http_response_code(400);
	echo json_encode(['error' => 'Pertanyaan terlalu panjang (maksimal 2000 karakter).']);
	exit;
}

// Obrolan tujuan: cek dulu apakah benar milik user ini, kalau tidak -> pakai obrolan terakhir.
$requestedId = (int) ($input['conversation_id'] ?? 0);
$conversationId = find_conversation($user['id'], $requestedId) ? $requestedId : current_conversation_id($user['id']);

// Konteks dari 6 pesan terakhir di obrolan itu, supaya AI ingat percakapan sebelumnya.
$recent = $conversationId > 0
	? get_conversation_messages($user['id'], $conversationId, 6)
	: get_chat_history($user['id'], 6);

$parts = [];
foreach ($recent as $turn) {
	$parts[] = ['text' => ($turn['role'] === 'user' ? 'Siswa' : 'Abe') . ': ' . $turn['message']];
}
$parts[] = ['text' => 'Siswa (' . $user['username'] . '): ' . $message];

$language = $user['language'] === 'en' ? 'English' : 'Bahasa Indonesia';
$systemPrompt = ABE_SYSTEM_PROMPT . "\n\nJawab dalam $language.";

try {
	$reply = call_gemini($systemPrompt, $parts);
} catch (GeminiException $ex) {
	http_response_code(502);
	echo json_encode(['error' => $ex->getMessage()]);
	exit;
}

save_chat_message($user['id'], 'user', $message, $conversationId);
save_chat_message($user['id'], 'ai', $reply, $conversationId);

// Pesan pertama di obrolan menentukan judulnya, biar mudah searched di Riwayat.
if ($conversationId > 0) {
	$conversation = find_conversation($user['id'], $conversationId);
	if ($conversation && count($recent) === 0) {
		rename_conversation($user['id'], $conversationId, $message);
	}
}

echo json_encode(['reply' => $reply, 'conversation_id' => $conversationId]);