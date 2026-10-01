<?php
require_once __DIR__ . '/history.php';

$pageTitle = 'Ask Our AI';
$tab = 'Ask Our AI';
$contentClass = 'chat-content';
require __DIR__ . '/partials/header.php';

// ?new=1 -> mulai obrolan kosong baru, lalu buang ?new dari URL supaya refresh tidak
// wiped bikin obrolan baru terus.
if (isset($_GET['new'])) {
	unset($_GET['new']);
	start_new_conversation($user['id']);
	header('Location: chat.php');
	exit;
}

$requestedId = isset($_GET['c']) ? (int) $_GET['c'] : 0;
$conversation = $requestedId > 0 ? find_conversation($user['id'], $requestedId) : null;

// id milik user lain / tidak ada -> jatuhkan ke obrolan terakhir milik user ini.
if ($conversation === null) {
	$conversationId = current_conversation_id($user['id']);
	$conversation = $conversationId > 0 ? find_conversation($user['id'], $conversationId) : null;
} else {
	$conversationId = (int) $conversation['id'];
}

$history = get_conversation_messages($user['id'], $conversationId);
$summaryHref = 'summary.php?from=chat&c=' . $conversationId;
$quizHref = 'quiz.php?from=chat&c=' . $conversationId;
?>
				<div class="chat-topbar">
					<span class="chat-title"><?= e($conversation['title'] ?? 'Obrolan Baru') ?></span>
					<a class="quick-nav-btn" href="chat.php?new=1">Obrolan Baru</a>
				</div>

				<div class="chat-log" id="chatLog" data-conversation="<?= (int) $conversationId ?>">
					<?php if (!$history): ?>
						<div class="msg msg-ai">
							<img class="chat-mascot" src="img/crocodile-mascot.jpeg" alt="">
							<div class="msg-text">Halo <?= e($user['username']) ?>! Tanyakan apa saja soal materi belajarmu, aku bantu jawab.</div>
						</div>
					<?php else: ?>
						<?php foreach ($history as $turn): ?>
							<div class="msg msg-<?= $turn['role'] === 'user' ? 'user' : 'ai' ?>">
								<?php if ($turn['role'] !== 'user'): ?>
									<img class="chat-mascot" src="img/crocodile-mascot.jpeg" alt="">
								<?php endif; ?>
								<div class="msg-text" data-raw="<?= e($turn['message']) ?>"></div>
							</div>
						<?php endforeach; ?>
					<?php endif; ?>
				</div>

				<?php if ($history): ?>
					<div class="chat-continue">
						<span>Lanjutkan dari obrolan ini:</span>
						<a class="quick-nav-btn" href="<?= e($summaryHref) ?>">Ringkas Obrolan</a>
						<a class="quick-nav-btn" href="<?= e($quizHref) ?>">Buat Kuis dari Obrolan</a>
					</div>
				<?php endif; ?>

				<form class="chat-form" id="chatForm">
					<input type="text" id="chatInput" placeholder="Tanyakan Apa Saja..." autocomplete="off" required maxlength="2000">
					<button type="submit" class="chat-send" id="chatSend" aria-label="Kirim">&#10148;</button>
				</form>
<?php require __DIR__ . '/partials/footer.php'; ?>
	<script>
		const log = document.getElementById('chatLog');
		const form = document.getElementById('chatForm');
		const input = document.getElementById('chatInput');
		const sendBtn = document.getElementById('chatSend');
		const conversationId = log.dataset.conversation;

		// Render ulang isi pesan dari riwayat (markdown ringan + aman dari HTML asing).
		document.querySelectorAll('.msg-text[data-raw]').forEach((el) => {
			el.innerHTML = formatText(el.dataset.raw);
		});
		log.scrollTop = log.scrollHeight;

		function addMessage(text, sender) {
			const row = document.createElement('div');
			row.className = 'msg msg-' + sender;
			const body = document.createElement('div');
			body.className = 'msg-text';

			if (sender === 'ai') {
				const mascot = document.createElement('img');
				mascot.className = 'chat-mascot';
				mascot.src = 'img/crocodile-mascot.jpeg';
				mascot.alt = '';
				row.appendChild(mascot);
				body.innerHTML = formatText(text);
			} else {
				body.textContent = text;
			}

			row.appendChild(body);
			log.appendChild(row);
			log.scrollTop = log.scrollHeight;
			return row;
		}

		function addLoadingRow() {
			const row = document.createElement('div');
			row.className = 'msg msg-ai';
			row.innerHTML = '<img class="chat-mascot" src="img/crocodile-mascot.jpeg" alt="">' +
				'<div class="pixel-progress"><div class="pixel-progress-fill"></div></div>';
			log.appendChild(row);
			log.scrollTop = log.scrollHeight;
			return row;
		}

		function addContinueBar() {
			if (document.querySelector('.chat-continue')) return;

			const bar = document.createElement('div');
			bar.className = 'chat-continue';
			bar.innerHTML = '<span>Lanjutkan dari obrolan ini:</span>' +
				'<a class="quick-nav-btn" href="summary.php?from=chat&c=' + encodeURIComponent(conversationId) + '">Ringkas Obrolan</a>' +
				'<a class="quick-nav-btn" href="quiz.php?from=chat&c=' + encodeURIComponent(conversationId) + '">Buat Kuis dari Obrolan</a>';
			form.parentNode.insertBefore(bar, form);
		}

		form.addEventListener('submit', async (e) => {
			e.preventDefault();
			const text = input.value.trim();
			if (!text) return;

			addMessage(text, 'user');
			input.value = '';
			input.disabled = true;
			sendBtn.disabled = true;
			const loadingRow = addLoadingRow();

			try {
				const res = await fetch('api/chat.php', {
					method: 'POST',
					headers: { 'Content-Type': 'application/json' },
					body: JSON.stringify({ message: text, conversation_id: conversationId })
				});
				const data = await res.json().catch(() => ({}));
				loadingRow.remove();

				if (!res.ok) {
					addMessage(data.error || 'Terjadi kesalahan, coba lagi.', 'ai');
				} else if (!data.reply) {
					addMessage('Balasan kosong dari server. Coba lagi.', 'ai');
				} else {
					addMessage(data.reply, 'ai');
					// Baru punya 1 obrolan -> tombol "lanjutkan ke Summary/Quiz" belum ada di DOM, tambahkan sekarang.
					addContinueBar();
				}
			} catch (err) {
				loadingRow.remove();
				addMessage('Tidak bisa terhubung ke server. Cek koneksi lalu coba lagi.', 'ai');
			} finally {
				input.disabled = false;
				sendBtn.disabled = false;
				input.focus();
			}
		});
	</script>
</body>
</html>
