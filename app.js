// Dipakai bersama oleh chat.php, summary.php, quiz.php untuk menampilkan teks dari Gemini
// (markdown ringan: paragraf, "- " jadi daftar poin, **tebal**) tanpa percaya HTML mentah dari AI.

function escapeHtml(str) {
	return str
		.replace(/&/g, '&amp;')
		.replace(/</g, '&lt;')
		.replace(/>/g, '&gt;')
		.replace(/"/g, '&quot;')
		.replace(/'/g, '&#39;');
}

function formatText(raw) {
	const lines = escapeHtml(raw || '').split(/\r?\n/);
	let html = '';
	let inList = false;
	let paragraph = [];

	const flushParagraph = () => {
		if (paragraph.length) {
			html += '<p>' + paragraph.join(' ') + '</p>';
			paragraph = [];
		}
	};

	lines.forEach((line) => {
		const trimmed = line.trim();
		const bullet = trimmed.match(/^[-*•]\s+(.*)/);

		if (bullet) {
			flushParagraph();
			if (!inList) {
				html += '<ul>';
				inList = true;
			}
			html += '<li>' + bullet[1] + '</li>';
			return;
		}

		if (inList) {
			html += '</ul>';
			inList = false;
		}

		if (trimmed === '') {
			flushParagraph();
			return;
		}

		paragraph.push(trimmed);
	});

	if (inList) html += '</ul>';
	flushParagraph();

	return html.replace(/\*\*(.+?)\*\*/g, '<strong>$1</strong>');
}