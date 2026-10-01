<?php
$pageTitle = 'Dashboard';
$showStrip = true;
require __DIR__ . '/partials/header.php';
?>
				<h1 class="welcome">Welcome to the Quest board, <span class="welcome-name"><?= e($user['username']) ?></span>! Hope you enjoy your study time with us!</h1>
				<div class="dashboard">
					<a class="tile" href="chat.php" aria-label="Ask our AI">
						<img class="ask-art" src="img/ask-our-ai.png" alt="Ask our AI">
						<span class="tile-label ask-label">Ask Our AI!</span>
					</a>
					<a class="tile summary" href="summary.php" aria-label="Summary">
						<img class="wide-art" src="img/summary-pic.png" alt="Summary">
						<span class="tile-label summary-label">Summary</span>
					</a>
					<a class="tile quiz" href="quiz.php" aria-label="Quiz time">
						<img class="wide-art" src="img/quiz-pic.png" alt="Quiz time">
						<span class="tile-label quiz-label">Quiz Time!</span>
					</a>
					<a class="tile games" href="games.php" aria-label="Games">
						<img class="wide-art" src="img/game-pic.png" alt="Games">
						<span class="tile-label games-label">Games</span>
					</a>
				</div>
<?php require __DIR__ . '/partials/footer.php'; ?>
</body>
</html>