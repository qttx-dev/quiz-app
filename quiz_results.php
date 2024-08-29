<?php
session_start();
require_once 'config.php';
checkUserRole(ROLE_USER);

$totalQuestions = count($_SESSION['quiz_questions']);
$correctAnswers = $_SESSION['correct_answers'];
$incorrectAnswers = $totalQuestions - $correctAnswers;
$percentage = ($correctAnswers / $totalQuestions) * 100;

// Animation
$confetti = "<script>
        confetti({
            particleCount: 100,
            spread: 70,
            origin: { y: 0.6 }
        });
    </script>";

function getQuestionDetails($db, $questionId) {
    try {
        $stmt = $db->prepare("
            SELECT q.question_text, a.id as answer_id, a.answer_text, a.is_correct, q.explanation
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
    <style>
        body {
            background-color: #f8f9fa;
            font-family: 'Arial', sans-serif;
        }
        .quiz-container {
            max-width: 800px;
            margin: auto;
            padding: 2rem;
            background: #ffffff;
            border-radius: 15px;
            box-shadow: 0 0.5rem 1rem rgba(0, 0, 0, 0.15);
        }
        .result-card {
            background-color: #fff;
            border-radius: 10px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            padding: 1.5rem;
            margin-bottom: 1.5rem;
        }
        .result-icon {
            font-size: 3rem;
            margin-bottom: 1rem;
        }
        .result-number {
            font-size: 2.5rem;
            font-weight: bold;
        }
        .result-text {
            font-size: 1.2rem;
        }
        .progress {
            height: 1.5rem;
            font-size: 1rem;
        }
        .question-card {
            background-color: #fff;
            border-radius: 10px;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
            padding: 1rem;
            margin-bottom: 1rem;
        }
        .correct-answer {
            color: #28a745;
            font-weight: bold;
        }
        .incorrect-answer {
            color: #dc3545;
        }
        .user-answer {
            background-color: #f8f9fa;
            padding: 0.5rem;
            border-radius: 5px;
        }

        ul {
    list-style-type: none; /* Entfernt die Standard-Aufzählungszeichen */
    padding-left: 0; /* Entfernt den linken Abstand */
    margin-bottom: 20px; /* Fügt einen Abstand nach unten hinzu */
}

ul li {
    position: relative; /* Ermöglicht die Verwendung von Pseudo-Elementen */
    padding-left: 20px; /* Abstand für das benutzerdefinierte Aufzählungszeichen */
    margin-bottom: 10px; /* Abstand zwischen den Listenelementen */
}

ul li::before {
    position: absolute; /* Positionierung */
    left: 0; /* Positionierung auf der linken Seite */
    color: black; /* Farbe des Aufzählungszeichens */
    font-size: 1em; /* Größe des Aufzählungszeichens, gleich der Textgröße */
    vertical-align: middle; /* Vertikale Ausrichtung */
}

    </style>
</head>
<body>
    <div class="container mt-4 mb-4">
        <div class="quiz-container">
            <h1 class="text-center mb-4">Quiz Ergebnisse</h1>
            <div class="row">
                <div class="col-md-4">
                    <div class="result-card text-center">
                        <i class="fas fa-check-circle result-icon text-success"></i>
                        <div class="result-number text-success"><?php echo $correctAnswers; ?></div>
                        <div class="result-text">Richtig</div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="result-card text-center">
                        <i class="fas fa-times-circle result-icon text-danger"></i>
                        <div class="result-number text-danger"><?php echo $incorrectAnswers; ?></div>
                        <div class="result-text">Falsch</div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="result-card text-center">
                        <i class="fas fa-percentage result-icon text-primary"></i>
                        <div class="result-number text-primary"><?php echo number_format($percentage, 1); ?>%</div>
                        <div class="result-text">Gesamt</div>
                    </div>
                </div>
            </div>

            <div class="progress mt-4 mb-4">
                <div class="progress-bar bg-success" role="progressbar" style="width: <?php echo $percentage; ?>%" aria-valuenow="<?php echo $percentage; ?>" aria-valuemin="0" aria-valuemax="100"><?php echo number_format($percentage, 1); ?>%</div>
            </div>

            <h2 class="text-center mb-4">Detaillierte Ergebnisse</h2>
            <?php foreach ($_SESSION['quiz_questions'] as $index => $question): ?>
                <div class="question-card">
                    <h5>Frage <?php echo $index + 1; ?></h5>
                    <p><?php
                    $questiontext = $question['question_text'];
                    // Erlaubte HTML-Tags
                    $allowed_tags = '<br><b><i><u><ol><ul><li></br></b></i></u></ol></ul></li>';

                    // Entfernen von nicht erlaubten Tags
                    $clean_text = strip_tags($questiontext, $allowed_tags);

                    echo $clean_text;

                    ?></p>
                    <?php 
                    $details = getQuestionDetails($db, $question['id']);
                    foreach ($details as $answer):
                        $class = $answer['is_correct'] ? 'correct-answer' : 'incorrect-answer';
                        $icon = $answer['is_correct'] ? '<i class="fas fa-check-circle result-icon text-success"></i>' : '<i class="fas fa-times-circle result-icon text-danger"></i>';
                        $userAnswer = $_SESSION['user_answers'][$index];
                        if ($userAnswer['answer_id'] == $answer['answer_id']) {
                            $class .= ' user-answer';
                        }
                    ?>
                        <p class="<?php echo $class; ?>"><?php echo htmlspecialchars($answer['answer_text']); ?></p>
                    <?php endforeach;
                    if (!empty($details[0]['explanation'])): ?>
                        <p class="mt-2"><strong>Erklärung:</strong> <?php echo htmlspecialchars($details[0]['explanation']); ?></p>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
            
            <div class="text-center mt-4">
                <a href="quiz.php" class="btn btn-primary">zurück</a>
                <a href="index.php" class="btn btn-secondary">zur Startseite</a>
            </div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.5.1.slim.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.5.3/dist/umd/popper.min.js"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/canvas-confetti@1.5.1/dist/confetti.browser.min.js"></script>
    <?php
    if($percentage > 50) {
        echo $confetti;
    }
    ?>
</body>
</html>
