<?php
session_start();
if (isset($_GET['restart'])) {
    unset($_SESSION['quiz_questions']);
    unset($_SESSION['current_question']);
    unset($_SESSION['correct_answers']);
    unset($_SESSION['user_answers']);
    header("Location: quiz.php");
    exit();
}
require_once 'config.php';

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Überprüfen, ob der Benutzer angemeldet ist
checkUserRole(ROLE_USER);

// Funktion zum Abrufen von Fragen aus ausgewählten Kategorien
function getQuestions($db, $categoryIds, $limit) {
    if (empty($categoryIds)) {
        return [];
    }

    $placeholders = implode(',', array_fill(0, count($categoryIds), '?'));
    try {
        $sql = "SELECT DISTINCT q.id, q.question_text
                FROM questions q
                JOIN question_categories qc ON q.id = qc.question_id
                WHERE qc.category_id IN ($placeholders)
                GROUP BY q.id
                ORDER BY RAND()
                LIMIT ?";
        $stmt = $db->prepare($sql);
        
        // Bind category IDs and limit
        foreach ($categoryIds as $index => $categoryId) {
            $stmt->bindValue($index + 1, $categoryId, PDO::PARAM_INT);
        }
        $stmt->bindValue(count($categoryIds) + 1, $limit, PDO::PARAM_INT);
        
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Fehler beim Abrufen der Fragen: " . $e->getMessage());
        return [];
    }
}

// Funktion zum Abrufen von Antworten für eine Frage
function getAnswers($db, $questionId) {
    try {
        $stmt = $db->prepare("SELECT id, answer_text, is_correct FROM answers WHERE question_id = ? ORDER BY RAND()");
        $stmt->execute([$questionId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Fehler beim Abrufen der Antworten: " . $e->getMessage());
        return [];
    }
}

// Funktion zum Abrufen aller Kategorien
function getCategories($db) {
    try {
        $stmt = $db->query("SELECT * FROM categories ORDER BY name");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Fehler beim Abrufen der Kategorien: " . $e->getMessage());
        return [];
    }
}

$categories = getCategories($db);
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['start_quiz'])) {
    $selectedCategories = isset($_POST['categories']) ? $_POST['categories'] : [];
    $questionCount = isset($_POST['question_count']) ? intval($_POST['question_count']) : 10;
    
    if (empty($selectedCategories)) {
        $error = "Bitte wählen Sie mindestens eine Kategorie aus.";
    } else {
        $questions = getQuestions($db, $selectedCategories, $questionCount);
        if (empty($questions)) {
            $error = "Keine Fragen für die ausgewählten Kategorien gefunden.";
        } else {
            $_SESSION['quiz_questions'] = $questions;
            $_SESSION['current_question'] = 0;
            $_SESSION['correct_answers'] = 0;
            $_SESSION['user_answers'] = [];
        }
    }
}

$currentQuestion = null;
$answers = null;

if (isset($_SESSION['quiz_questions']) && isset($_SESSION['current_question'])) {
    if ($_SESSION['current_question'] < count($_SESSION['quiz_questions'])) {
        $currentQuestion = $_SESSION['quiz_questions'][$_SESSION['current_question']];
        $answers = getAnswers($db, $currentQuestion['id']);
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_answer'])) {
    $selectedAnswerId = $_POST['answer'];
    $stmt = $db->prepare("SELECT is_correct FROM answers WHERE id = ?");
    $stmt->execute([$selectedAnswerId]);
    $isCorrect = $stmt->fetchColumn();

    $_SESSION['user_answers'][] = [
        'question_id' => $currentQuestion['id'],
        'answer_id' => $selectedAnswerId,
        'is_correct' => $isCorrect
    ];

    if ($isCorrect) {
        $_SESSION['correct_answers']++;
    }

    $_SESSION['current_question']++;

    // Redirect to avoid form resubmission
    header("Location: quiz.php");
    exit();
}
?>

<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Quiz - Quiz App</title>
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css">
    <link rel="stylesheet" href="styles.css">
    <script src="https://cdn.jsdelivr.net/npm/canvas-confetti@1.5.1/dist/confetti.browser.min.js"></script>
</head>
<body>
    <div class="container mt-5">
    <div class="text-center mb-4">
    <i class="fas fa-user-graduate welcome-icon"></i> <!-- Neues Willkommen-Icon -->
        <h1 class="text-center mb-4">Quiz App</h1>
    </div>
        <?php if ($error): ?>
            <div class="alert alert-danger"><?php echo $error; ?></div>
        <?php endif; ?>

        <?php if (!isset($_SESSION['quiz_questions'])): ?>
        <form method="post" action="">
            <div class="form-group">
                <label>Wähle Kategorien</label>
                <div class="row">
                    <?php foreach ($categories as $category): ?>
                        <div class="col-md-4 mb-2">
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" role="switch" name="categories[]" value="<?php echo $category['id']; ?>" id="category<?php echo $category['id']; ?>">
                                <label class="form-check-label" for="category<?php echo $category['id']; ?>">
                                    <?php echo htmlspecialchars($category['name']); ?>
                                </label>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <div class="form-group">
                <div class="text-center mb-4">
                    <label for="question_count" class="form-label">Anzahl der Fragen</label>
                    <input type="number" class="form-control form-control-lg mx-auto text-center" id="question_count" name="question_count" required>
                </div>
            </div>
            <button type="submit" name="start_quiz" class="btn btn-primary btn-custom mb-3"><i class="fas fa-play"></i> Quiz starten</button>
        </form>
        <?php elseif ($currentQuestion): ?>
        <div class="card">
            <div class="card-body">
                <h5 class="card-title text-center">Frage <?php echo $_SESSION['current_question'] + 1; ?> von <?php echo count($_SESSION['quiz_questions']); ?></h5>
                <p class="card-text"><?php echo htmlspecialchars($currentQuestion['question_text']); ?></p>
                <form method="post" action="">
                    <?php foreach ($answers as $answer): ?>
                        <div class="form-check mb-2">
                            <input class="form-check-input" type="radio" name="answer" value="<?php echo $answer['id']; ?>" id="answer<?php echo $answer['id']; ?>" required>
                            <label class="form-check-label" for="answer<?php echo $answer['id']; ?>">
                                <?php echo htmlspecialchars($answer['answer_text']); ?>
                            </label>
                        </div>
                    <?php endforeach; ?>
                    <button type="submit" name="submit_answer" class="btn btn-primary mt-3 w-100"><i class="fas fa-check"></i> Antwort bestätigen</button>
                </form>
            </div>
        </div>
        <?php else: ?>
        <div class="alert alert-success text-center">
            <h2><i class="fas fa-trophy"></i> Quiz beendet!</h2>
            <p>Sie haben <?php echo $_SESSION['correct_answers']; ?> von <?php echo count($_SESSION['quiz_questions']); ?> Fragen richtig beantwortet.</p>
            <a href="quiz_results.php" class="btn btn-primary btn-custom mb-3"><i class="fas fa-chart-bar"></i> Detaillierte Ergebnisse</a>
            <a href="quiz.php?restart=1" class="btn btn-secondary btn-custom mb-3"><i class="fas fa-redo"></i> Quiz neu starten</a>
            <a href="index.php" class="btn btn-secondary btn-custom mb-3"><i class="fas fa-home"></i> Zurück zur Startseite</a>
        </div>
        <script>
            confetti({
                particleCount: 100,
                spread: 70,
                origin: { y: 0.6 }
            });
        </script>
        <?php endif; ?>
    </div>
    <?php include 'footer.php'; ?>
    <script src="https://code.jquery.com/jquery-3.5.1.slim.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.5.3/dist/umd/popper.min.js"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>
    <script src="app.js"></script>
</body>
</html>
