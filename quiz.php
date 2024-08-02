<?php
session_start();
require_once 'config.php';

// Überprüfen, ob der Benutzer angemeldet ist
checkUserRole(ROLE_USER);

// Funktion zum Abrufen von Fragen
function getQuestions($db, $category_ids, $limit) {
    $placeholders = implode(',', array_fill(0, count($category_ids), '?'));

    $sql = "SELECT q.id, q.question_text, COALESCE(us.correct_count, 0) as correct_count, COALESCE(us.incorrect_count, 0) as incorrect_count
            FROM questions q
            JOIN question_categories qc ON q.id = qc.question_id
            LEFT JOIN user_statistics us ON q.id = us.question_id AND us.user_id = ?
            WHERE qc.category_id IN ($placeholders)
            GROUP BY q.id
            ORDER BY (COALESCE(us.incorrect_count, 0) - COALESCE(us.correct_count, 0)) DESC, RAND()
            LIMIT ?";

    $stmt = $db->prepare($sql);

    // Binden Sie die Parameter
    $user_id = $_SESSION['user_id'];
    $params = array_merge([$user_id], $category_ids, [$limit]);
    
    // Verwenden Sie bindValue für PDO
    $stmt->bindValue(1, $user_id, PDO::PARAM_INT);
    foreach ($category_ids as $key => $category_id) {
        $stmt->bindValue($key + 2, $category_id, PDO::PARAM_INT);
    }
    $stmt->bindValue(count($params), $limit, PDO::PARAM_INT);

    $stmt->execute();

    $questions = $stmt->fetchAll(PDO::FETCH_ASSOC);

    return $questions;
}

// Funktion zum Abrufen von Antworten für eine Frage
function getAnswers($db, $questionId) {
    try {
        $stmt = $db->prepare("SELECT id, answer_text, is_correct FROM answers WHERE question_id = ? ORDER BY RAND()");
        $stmt->execute([$questionId]);
        $answers = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Mischen Sie die Antworten nur, wenn sie noch nicht in der Session gespeichert sind
        if (!isset($_SESSION['shuffled_answers'][$questionId])) {
            shuffle($answers);
            $_SESSION['shuffled_answers'][$questionId] = $answers;
        } else {
            $answers = $_SESSION['shuffled_answers'][$questionId];
        }
        
        // Mischen der Antworten
        // shuffle($answers);
        return $answers;

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

// Funktion zum Aktualisieren der Benutzerstatistik
function updateUserStatistics($db, $user_id, $question_id, $is_correct) {
    $stmt = $db->prepare("INSERT INTO user_statistics (user_id, question_id, correct_count, incorrect_count) 
                          VALUES (?, ?, ?, ?) 
                          ON DUPLICATE KEY UPDATE 
                          correct_count = correct_count + ?, 
                          incorrect_count = incorrect_count + ?");
    $correct_increment = $is_correct ? 1 : 0;
    $incorrect_increment = $is_correct ? 0 : 1;
    $stmt->execute([$user_id, $question_id, $correct_increment, $incorrect_increment, $correct_increment, $incorrect_increment]);
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
            $_SESSION['shuffled_answers'] = [];
        }
    }
}

$currentQuestion = null;
$answers = null;

// if (isset($_SESSION['quiz_questions']) && isset($_SESSION['current_question'])) {
//     if ($_SESSION['current_question'] < count($_SESSION['quiz_questions'])) {
//         $currentQuestion = $_SESSION['quiz_questions'][$_SESSION['current_question']];

//         if (!isset($_SESSION['shuffled_answers'][$currentQuestion['id']])) {
//             $answers = getAnswers($db, $currentQuestion['id']);
//             $_SESSION['shuffled_answers'][$currentQuestion['id']] = $answers;

//         else {
//             $answers = $_SESSION['shuffled_answers'][$currentQuestion['id']];
//         }

//     }
// }

// if (isset($_SESSION['quiz_questions']) && isset($_SESSION['current_question'])) {
//     if ($_SESSION['current_question'] < count($_SESSION['quiz_questions'])) {
//         $currentQuestion = $_SESSION['quiz_questions'][$_SESSION['current_question']];
//         if (!isset($_SESSION['shuffled_answers'][$currentQuestion['id']])) {
//             $answers = getAnswers($db, $currentQuestion['id']);
//             $_SESSION['shuffled_answers'][$currentQuestion['id']] = $answers;
//         } else {
//             $answers = $_SESSION['shuffled_answers'][$currentQuestion['id']];
//         }
//     }
// }

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
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    $isCorrect = $result['is_correct'];

    $_SESSION['user_answers'][] = [
        'question_id' => $currentQuestion['id'],
        'answer_id' => $selectedAnswerId,
        'is_correct' => $isCorrect
    ];

    if ($isCorrect) {
        $_SESSION['correct_answers']++;
    }

    // Richtige Antwort abrufen
    $stmtCorrect = $db->prepare("SELECT id FROM answers WHERE question_id = ? AND is_correct = 1");
    $stmtCorrect->execute([$currentQuestion['id']]);
    $correctAnswerId = $stmtCorrect->fetchColumn();

    $_SESSION['show_result'] = true;
    $_SESSION['selected_answer_id'] = $selectedAnswerId;
    $_SESSION['correct_answer_id'] = $correctAnswerId;

    updateUserStatistics($db, $_SESSION['user_id'], $currentQuestion['id'], $isCorrect);

    // Kein automatisches Weiterleiten zur nächsten Frage
    header("Location: quiz.php");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['next_question'])) {
    $_SESSION['current_question']++;
    $_SESSION['show_result'] = false;
    header("Location: quiz.php");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['restart_quiz'])) {
    unset($_SESSION['quiz_questions']);
    unset($_SESSION['current_question']);
    unset($_SESSION['correct_answers']);
    unset($_SESSION['user_answers']);
    unset($_SESSION['show_result']);
    unset($_SESSION['confetti']);
    header("Location: quiz.php");
    exit();
}
?>

<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Quiz App</title>
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
        .btn-answer {
            font-size: 1.1rem;
            padding: 1rem;
            margin-bottom: 1rem;
            text-align: left;
            white-space: normal;
            border: 2px solid #007bff;
            border-radius: 10px;
            transition: all 0.3s ease;
        }
        .btn-answer:hover {
            transform: translateY(-3px);
            box-shadow: 0 4px 8px rgba(0,0,0,0.1);
        }
        .btn-answer.correct {
            background-color: #28a745;
            border-color: #28a745;
            color: white;
        }
        .btn-answer.incorrect {
            background-color: #dc3545;
            border-color: #dc3545;
            color: white;
        }
        .question-number {
            font-size: 1.2rem;
            color: #007bff;
            margin-bottom: 1rem;
        }
        .category-badge {
            font-size: 0.9rem;
            margin-right: 0.5rem;
            background-color: #17a2b8;
            color: white;
            padding: 0.3rem 0.6rem;
            border-radius: 20px;
        }
        .custom-control-label {
            padding-left: 0.5rem;
        }

        .question {
            font-size: 1.5em;
            font-weight: bold;
        }

        @media (max-width: 576px) {
            .quiz-container {
                padding: 1rem;
            }
            .btn-answer {
                font-size: 1rem;
                padding: 0.8rem;
            }
        }
    </style>
</head>
<body>
    <div class="container mt-4 mb-4">
        <div class="quiz-container">
            <?php if ($error): ?>
                <div class="alert alert-danger" role="alert">
                    <i class="fas fa-exclamation-triangle"></i> <?php echo $error; ?>
                </div>
            <?php elseif (!isset($_SESSION['quiz_questions'])): ?>
                <h2 class="text-center mb-4"><i class="fas fa-question-circle"></i> Quiz starten</h2>
                <form method="post" action="">
                    <div class="form-group">
                        <label for="question_count"><i class="fas fa-list-ol"></i> Anzahl der Fragen:</label>
                        <input type="number" class="form-control form-control-lg" id="question_count" name="question_count" min="1" max="50" value="10" required>
                    </div>
                    <div class="form-group">
                        <label><i class="fas fa-tags"></i> Kategorien auswählen:</label>
                        <div class="row">
                            <?php foreach ($categories as $category): ?>
                                <div class="col-md-6">
                                    <div class="custom-control custom-checkbox mb-2">
                                        <input type="checkbox" class="custom-control-input" name="categories[]" value="<?php echo $category['id']; ?>" id="category<?php echo $category['id']; ?>">
                                        <label class="custom-control-label" for="category<?php echo $category['id']; ?>">
                                            <?php echo htmlspecialchars($category['name']); ?>
                                        </label>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <button type="submit" name="start_quiz" class="btn btn-primary btn-lg btn-block">
                        <i class="fas fa-play"></i> Quiz starten
                    </button>
                </form>
            <?php elseif ($currentQuestion): ?>
                <div class="question-number text-center">
                    <i class="fas fa-question-circle"></i> Frage <?php echo $_SESSION['current_question'] + 1; ?> von <?php echo count($_SESSION['quiz_questions']); ?>
                </div>
                <h3 class="mb-4 question"><?php echo htmlspecialchars($currentQuestion['question_text']); ?></h3>
                <?php if (isset($_SESSION['show_result']) && $_SESSION['show_result']): ?>
                    <?php foreach ($answers as $answer): ?>
                        <button class="btn btn-answer btn-block <?php 
                            if ($answer['id'] == $_SESSION['correct_answer_id']) echo 'correct';
                            elseif ($answer['id'] == $_SESSION['selected_answer_id'] && $answer['id'] != $_SESSION['correct_answer_id']) echo 'incorrect';
                        ?>" disabled>
                            <?php echo htmlspecialchars($answer['answer_text']); ?>
                        </button>
                    <?php endforeach; ?>
                    <form method="post" action="">
                        <button type="submit" name="next_question" class="btn btn-primary btn-lg btn-block mt-3">
                            <i class="fas fa-arrow-right"></i> Nächste Frage
                        </button>
                    </form>
                <?php else: ?>
                    <form method="post" action="">
                        <?php foreach ($answers as $answer): ?>
                            <button type="submit" name="answer" value="<?php echo $answer['id']; ?>" class="btn btn-answer btn-block">
                                <?php echo htmlspecialchars($answer['answer_text']); ?>
                            </button>
                        <?php endforeach; ?>
                        <input type="hidden" name="submit_answer" value="1">
                    </form>
                <?php endif; ?>
            <?php else: ?>
                <?php
                $_SESSION['confetti'] = 1;
                $totalQuestions = count($_SESSION['quiz_questions']);
                $correctAnswers = $_SESSION['correct_answers'];
                $incorrectAnswers = $totalQuestions - $correctAnswers;
                $percentage = ($correctAnswers / $totalQuestions) * 100;
                if($percentage > 50)
                {
                    $score = 1;
                }
                else
                {
                    $score = 0;
                }
                ?>
                <h2 class="text-center mb-4"><i class="fas fa-clipboard-check"></i> Quiz beendet</h2>
                <p class="lead text-center">
                    Sie haben <?php echo $_SESSION['correct_answers']; ?> von <?php echo count($_SESSION['quiz_questions']); ?> Fragen richtig beantwortet.
                </p>
                <div class="text-center mt-4">
                    <a href="quiz_results.php" class="btn btn-info btn-lg btn-block mb-2">
                        <i class="fas fa-chart-bar"></i> Detaillierte Ergebnisse anzeigen
                    </a>
                    <form method="post" action="">
                        <button type="submit" name="restart_quiz" class="btn btn-primary btn-lg btn-block mb-2">
                            <i class="fas fa-redo"></i> Quiz neu starten
                        </button>
                    </form>
                    <a href="index.php" class="btn btn-secondary btn-lg btn-block">
                        <i class="fas fa-home"></i> Zurück zur Startseite
                    </a>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.5.1.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.5.3/dist/umd/popper.min.js"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/canvas-confetti@1.5.1/dist/confetti.browser.min.js"></script>
    <script>
    $(document).ready(function() {
        <?php if (isset($_SESSION['confetti']) && $score == 1): ?>
        confetti({
            particleCount: 100,
            spread: 70,
            origin: { y: 0.6 }
        });
        <?php endif; ?>
    });
    </script>
</body>
</html>
