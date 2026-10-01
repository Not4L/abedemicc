<?php
require_once __DIR__ . '/history.php';

$pageTitle = 'Quiz Time';
$tab = 'Quiz Time';
$contentClass = 'quiz-content';
require __DIR__ . '/partials/header.php';

$viewId = isset($_GET['view']) ? (int) $_GET['view'] : null;
$viewedQuiz = $viewId ? get_quiz($user['id'], $viewId) : null;
$autoFromChat = isset($_GET['from']) && $_GET['from'] === 'chat' && !$viewedQuiz;
// Obrolan asal saat tombol "Buat Kuis dari Obrolan" ditekan.
$chatConversation = isset($_GET['c']) && find_conversation($user['id'], (int) $_GET['c']) ? (int) $_GET['c'] : 0;
$recentQuizzes = get_quizzes($user['id']);
?>
				<img class="page-avatar" src="img/rimuru-mascot.jpeg" alt="">

				<div id="quizUpload" class="quiz-stage" <?= ($viewedQuiz || $autoFromChat) ? 'hidden' : '' ?>>
					<label class="dropzone" for="quizFile">
						<img src="img/folder-pic.jpeg" alt="">
						<span id="quizFileLabel">DROP FILE HERE</span>
					</label>
					<input type="file" id="quizFile" accept=".txt,.pdf,.png,.jpg,.jpeg,.webp" hidden>
					<p class="stage-error" id="quizError" hidden></p>
					<button type="button" class="login-button" id="quizGenerate" disabled>Generate Quiz</button>

					<?php if ($recentQuizzes): ?>
						<div class="history-list">
							<h3>Riwayat Kuis</h3>
							<ul>
								<?php foreach ($recentQuizzes as $item): ?>
									<li><a href="quiz.php?view=<?= (int) $item['id'] ?>"><?= e($item['source_label']) ?></a>
										<span><?= e(date('d M, H:i', strtotime($item['created_at']))) ?></span></li>
								<?php endforeach; ?>
							</ul>
						</div>
					<?php endif; ?>
				</div>

				<div id="quizLoading" class="quiz-stage" <?= $autoFromChat ? '' : 'hidden' ?>>
					<div class="pixel-progress pixel-progress-lg"><div class="pixel-progress-fill"></div></div>
					<p class="loading-label">Abe sedang menyusun soal...</p>
				</div>

				<div id="quizQuestion" class="quiz-stage" <?= $viewedQuiz ? '' : 'hidden' ?>>
					<div class="quiz-box" id="quizQuestionText"></div>
					<div class="quiz-options" id="quizOptions"></div>
				</div>

				<div id="quizExplain" class="quiz-stage" hidden>
					<div class="quiz-explain-box">
						<h3>Explanation:</h3>
						<div class="quiz-explain-text" id="quizExplainText"></div>
					</div>
					<button type="button" class="login-button" id="quizNext">Next Question</button>
				</div>
<?php require __DIR__ . '/partials/footer.php'; ?>
	<script>
		const fileInput = document.getElementById('quizFile');
		const fileLabel = document.getElementById('quizFileLabel');
		const generateBtn = document.getElementById('quizGenerate');
		const errorBox = document.getElementById('quizError');
		const uploadStage = document.getElementById('quizUpload');
		const loadingStage = document.getElementById('quizLoading');
		const qStage = document.getElementById('quizQuestion');
		const eStage = document.getElementById('quizExplain');
		const qText = document.getElementById('quizQuestionText');
		const qOptions = document.getElementById('quizOptions');
		const eText = document.getElementById('quizExplainText');

		let questions = <?= json_encode($viewedQuiz['questions'] ?? []) ?>;
		let current = 0;

		fileInput.addEventListener('change', () => {
			generateBtn.disabled = !fileInput.files.length;
			fileLabel.textContent = fileInput.files.length ? fileInput.files[0].name : 'DROP FILE HERE';
		});

		async function generateQuiz(formData) {
			errorBox.hidden = true;
			uploadStage.hidden = true;
			qStage.hidden = true;
			eStage.hidden = true;
			loadingStage.hidden = false;

			try {
				const res = await fetch('api/quiz.php', { method: 'POST', body: formData });
				const data = await res.json();

				if (!res.ok) {
					throw new Error(data.error || 'Gagal membuat kuis.');
				}

				questions = data.questions;
				current = 0;
				loadingStage.hidden = true;
				renderQuestion();
				qStage.hidden = false;
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
			generateQuiz(formData);
		});

		function renderQuestion() {
			// Reset total: hapus pilihan/warna/penjelasan soal sebelumnya supaya tidak ada sisa dari jawaban lama.
			eText.textContent = '';
			const item = questions[current];
			if (!item) return;
			qText.textContent = item.question;
			qOptions.innerHTML = '';
			const options = Array.isArray(item.options) ? item.options : [];
			const correctIndex = Number(item.correctIndex);

			options.forEach((opt, i) => {
				const btn = document.createElement('button');
				btn.className = 'option';
				btn.textContent = String.fromCharCode(65 + i) + '. ' + opt;
				btn.addEventListener('click', () => {
					// Kunci semua tombol, tandai yang benar (hijau) dan yang dipilih kalau salah (merah).
					qOptions.querySelectorAll('.option').forEach((b) => { b.disabled = true; });
					const correctBtn = qOptions.children[correctIndex];
					if (correctBtn) {
						correctBtn.classList.add('correct');
					}
					if (i !== correctIndex) {
						btn.classList.add('wrong');
					}

					eText.innerHTML = formatText(item.explanation);
					// Soal + pilihan tetap terlihat, penjelasan muncul DI BAWAHNYA.
					// Kalau soalnya disembunyikan, kita lupa apa yang tadi ditanya dan
					// jawaban mana yang sudah dipilih.
					eStage.hidden = false;
					setTimeout(() => {
						eStage.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
					}, 600);
				});
				qOptions.appendChild(btn);
			});
		}

		document.getElementById('quizNext').addEventListener('click', () => {
			current += 1;
			eStage.hidden = true;

			if (current >= questions.length) {
				// Habis, balik ke upload untuk materi baru.
				fileInput.value = '';
				fileLabel.textContent = 'DROP FILE HERE';
				generateBtn.disabled = true;
				qStage.hidden = true;
				uploadStage.hidden = false;
				history.replaceState(null, '', 'quiz.php');
				return;
			}

			renderQuestion();
			qStage.hidden = false;
		});

		if (questions.length) {
			renderQuestion();
		}

		<?php if ($autoFromChat): ?>
		// Datang dari tombol "Buat Kuis dari Obrolan" di halaman chat -> langsung generate.
		const chatFormData = new FormData();
		chatFormData.append('source', 'chat');
		chatFormData.append('conversation_id', '<?= (int) $chatConversation ?>');
		generateQuiz(chatFormData);
		<?php endif; ?>
	</script>
</body>
</html>