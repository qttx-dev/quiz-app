<?php
session_start();
require_once 'config.php';
// Überprüfen, ob der Benutzer angemeldet ist
checkUserRole(ROLE_USER);

// if (!isset($_SESSION['quiz_finished']) || !$_SESSION['quiz_finished']) {
//     header("Location: quiz.php");
//     exit();
// }

$totalQuestions = count($_SESSION['quiz_questions']);
$correctAnswers = $_SESSION['correct_answers'];
$percentage = ($correctAnswers / $totalQuestions) * 100;

$showSummary = isset($_GET['show_summary']) && $_GET['show_summary'] == 1;

// Funktion zum Abrufen der Details einer Frage
function getQuestionDetails($db, $questionId) {
    try {
        $stmt = $db->prepare("
            SELECT q.question_text, a.answer_text, a.is_correct, q.explanation
            FROM questions q
            JOIN answers a ON q.id = a.question_id
            WHERE q.id = ?
        ");
        $stmt->execute([$questionId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Fehler beim Abrufen der Fragedetails: " . $e->getMessage());
        return [];
    }
}

?>

<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Quiz Ergebnisse - Quiz App</title>
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css">
    <link rel="stylesheet" href="styles.css">
</head>
<body>
    <div class="container mt-5">
        <div class="text-center mb-4">
            <i class="fas fa-trophy quiz-icon"></i>
            <h1 class="mb-4">Quiz Ergebnisse</h1>
        </div>
        
        <div class="card">
            <div class="card-body text-center">
                <?php if ($showSummary): ?>
                    <h2>Quiz beendet!</h2>
                    <p class="lead">Sie haben <?php echo $correctAnswers; ?> von <?php echo $totalQuestions; ?> Fragen richtig beantwortet.</p>
                    <h3><?php echo number_format($percentage, 2); ?> %</h3>
                    <a href="quiz_results.php" class="btn btn-primary btn-custom mt-3">Details anzeigen</a>
                <?php else: ?>
                    <h2>Detaillierte Ergebnisse</h2>
                    <?php foreach ($_SESSION['quiz_questions'] as $index => $question): ?>
                        <div class="card mt-4">
                            <div class="card-body">
                                <h5 class="card-title">Frage <?php echo $index + 1; ?></h5>
                                <p class="card-text"><?php echo htmlspecialchars($question['question_text']); ?></p>
                                <?php 
                                $details = getQuestionDetails($db, $question['id']);
                                foreach ($details as $answer):
                                    $class = $answer['is_correct'] ? 'correct-answer' : 'incorrect-answer';
                                ?>
                                    <p class="<?php echo $class; ?>"><?php echo htmlspecialchars($answer['answer_text']); ?></p>
                                <?php endforeach; ?>
                                <?php if (!empty($details[0]['explanation'])): ?>
                                    <p><strong>Erklärung</strong><br> <?php echo htmlspecialchars($details[0]['explanation']); ?></p>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
        
        <div class="text-center mt-4 mb-4">
            <a href="index.php" class="btn btn-secondary btn-custom">Zurück zur Startseite</a>
        </div>
    </div>

    <canvas id="confetti-canvas"></canvas>

    <script src="https://code.jquery.com/jquery-3.5.1.slim.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.5.3/dist/umd/popper.min.js"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/canvas-confetti@1.5.1/dist/confetti.browser.min.js"></script>
    <script>
        <?php if ($showSummary): ?>
        // Konfetti-Effekt
        var duration = 15 * 1000;
        var animationEnd = Date.now() + duration;
        var defaults = { startVelocity: 30, spread: 360, ticks: 60, zIndex: 0 };

        function randomInRange(min, max) {
            return Math.random() * (max - min) + min;
        }

        var interval = setInterval(function() {
            var timeLeft = animationEnd - Date.now();

            if (timeLeft <= 0) {
                return clearInterval(interval);
            }

            var particleCount = 50 * (timeLeft / duration);
            confetti(Object.assign({}, defaults, { particleCount, origin: { x: randomInRange(0.1, 0.3), y: Math.random() - 0.2 } }));
            confetti(Object.assign({}, defaults, { particleCount, origin: { x: randomInRange(0.7, 0.9), y: Math.random() - 0.2 } }));
        }, 250);
        <?php endif; ?>
    </script>
</body>
</html>
