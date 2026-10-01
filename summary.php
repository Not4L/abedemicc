<?php
require_once __DIR__ . '/history.php';

$pageTitle = 'Summary';
$tab = 'Summary';
$contentClass = 'summary-content';
require __DIR__ . '/partials/header.php';

$viewId = isset($_GET['view']) ? (int) $_GET['view'] : null;
$viewedSummary = $viewId ? get_summary($user['id'], $viewId) : null;
$autoFromChat = isset($_GET['from']) && $_GET['from'] === 'chat' && !$viewedSummary;
// Obrolan asal saat tombol "Ringkas Obrolan" ditekan, supaya cuma obrolan itu yang diringkas.
$chatConversation = isset($_GET['c']) && find_conversation($user['id'], (int) $_GET['c']) ? (int) $_GET['c'] : 0;
$recentSummaries = get_summaries($user['id']);
?>
				<img class="page-avatar" src="img/rimuru-mascot.jpeg" alt="">

				<div id="summaryUpload" class="summary-stage" <?= ($viewedSummary || $autoFromChat) ? 'hidden' : '' ?>>
					<label class="dropzone" for="summaryFile">
						<img src="img/folder-pic.jpeg" alt="">
						<span id="summaryFileLabel">DROP FILE HERE</span>
					</label>
					<input type="file" id="summaryFile" accept=".txt,.pdf,.png,.jpg,.jpeg,.webp" hidden>
					<p class="stage-error" id="summaryError" hidden></p>
					<button type="button" class="login-button" id="summaryGenerate" disabled>Generate Summary</button>

					<?php if ($recentSummaries): ?>
						<div class="history-list">
							<h3>Riwayat Ringkasan</h3>
							<ul>
								<?php foreach ($recentSummaries as $item): ?>
									<li><a href="summary.php?view=<?= (int) $item['id'] ?>"><?= e($item['source_label']) ?></a>
										<span><?= e(date('d M, H:i', strtotime($item['created_at']))) ?></span></li>
								<?php endforeach; ?>
							</ul>
						</div>
					<?php endif; ?>
				</div>

				<div id="summaryLoading" class="summary-stage" <?= $autoFromChat ? '' : 'hidden' ?>>
					<div class="pixel-progress pixel-progress-lg"><div class="pixel-progress-fill"></div></div>
					<p class="loading-label">Abe sedang membaca materimu...</p>
				</div>

				<div id="summaryResult" class="summary-stage" <?= $viewedSummary ? '' : 'hidden' ?>>
					<div class="result-panel">
						<h2 id="summarySource"><?= e($viewedSummary['source_label'] ?? 'Ringkasan Materi') ?></h2>
						<div id="summaryText" data-raw="<?= e($viewedSummary['content'] ?? '') ?>"></div>
					</div>
					<button type="button" class="login-button" id="summaryReset">Ringkas File Lain</button>
				</div>
<?php require __DIR__ . '/partials/footer.php'; ?>
	<script>
		const fileInput = document.getElementById('summaryFile');
		const fileLabel = document.getElementById('summaryFileLabel');
		const generateBtn = document.getElementById('summaryGenerate');
		const errorBox = document.getElementById('summaryError');
		const uploadStage = document.getElementById('summaryUpload');
		const loadingStage = document.getElementById('summaryLoading');
		const resultStage = document.getElementById('summaryResult');
		const sourceEl = document.getElementById('summarySource');
		const textEl = document.getElementById('summaryText');

		// Render ringkasan yang sudah ada (dari riwayat, atau baru dibuat) sebagai markdown ringan.
		if (textEl.dataset.raw) {
			textEl.innerHTML = formatText(textEl.dataset.raw);
		}

		fileInput.addEventListener('change', () => {
			generateBtn.disabled = !fileInput.files.length;
			fileLabel.textContent = fileInput.files.length ? fileInput.files[0].name : 'DROP FILE HERE';
		});

		async function generateSummary(formData) {
			errorBox.hidden = true;
			uploadStage.hidden = true;
			resultStage.hidden = true;
			loadingStage.hidden = false;

			try {
				const res = await fetch('api/summary.php', { method: 'POST', body: formData });
				const data = await res.json();

				if (!res.ok) {
					throw new Error(data.error || 'Gagal membuat ringkasan.');
				}

				sourceEl.textContent = data.source;
				textEl.innerHTML = formatText(data.summary);
				loadingStage.hidden = true;
				resultStage.hidden = false;
			} catch (err) {
				loadingStage.hidden = true;
				uploadStage.hidden = false;
				errorBox.textContent = err.message;
				errorBox.hidden = false;
			}
		}

		generateBtn.addEventListener('click', () => {
			const formData = new FormData();
			formData.append('source', 'file');
			formData.append('file', fileInput.files[0]);
			generateSummary(formData);
		});

		document.getElementById('summaryReset').addEventListener('click', () => {
			fileInput.value = '';
			fileLabel.textContent = 'DROP FILE HERE';
			generateBtn.disabled = true;
			resultStage.hidden = true;
			uploadStage.hidden = false;
			history.replaceState(null, '', 'summary.php');
		});

		<?php if ($autoFromChat): ?>
		// Datang dari tombol "Ringkas Obrolan" di halaman chat -> langsung generate, tidak perlu upload.
		const chatFormData = new FormData();
		chatFormData.append('source', 'chat');
		chatFormData.append('conversation_id', '<?= (int) $chatConversation ?>');
		generateSummary(chatFormData);
		<?php endif; ?>
	</script>
</body>
</html>