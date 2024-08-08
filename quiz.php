<?php
session_start();
require_once 'config.php';

// Zufallsgenerator - für Versionen >PHP 7.1
srand(time());


// Überprüfen, ob der Benutzer angemeldet ist
checkUserRole(ROLE_USER);
error_log("Session state 1: " . print_r($_SESSION, true));
// Initialisieren von VAR
$userId = $_SESSION['user_id'];
$error = '';
error_log("Session state 2: " . print_r($_SESSION, true));

// Funktion zum Abrufen der Kategorien, auf die der Benutzer Zugriff hat
function getCategories($db, $userId) {
    try {
        $stmt = $db->prepare("
            SELECT DISTINCT c.*
            FROM categories c
            INNER JOIN user_categories uc ON c.id = uc.category_id
            WHERE uc.user_id = ?
            ORDER BY c.name
        ");
        $stmt->execute([$userId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Fehler beim Abrufen der Kategorien: " . $e->getMessage());
        return [];
    }
}

// Funktion zum Abrufen von Fragen
function getQuestions($db, $category_ids, $limit) {
    error_log("Selected questions: " . print_r($questions, true));
    $placeholders = implode(',', array_fill(0, count($category_ids), '?'));
    $user_id = $_SESSION['user_id'];

    // Bestimme Wiederholungsintervalle basierend auf Benutzereinstellungen
    $repeat_interval_correct = getRepeatIntervalForUser($db, $user_id, 'correct');
    $repeat_interval_incorrect = getRepeatIntervalForUser($db, $user_id, 'incorrect');    

    // SQL für neue und unbeantwortete Fragen
    $sql_new = "SELECT q.id, q.question_text, 
                0 as correct_count, 
                0 as incorrect_count,
                0 as view_count,
                '1970-01-01' as last_shown
        FROM questions q
        JOIN question_categories qc ON q.id = qc.question_id
        LEFT JOIN user_statistics us ON q.id = us.question_id AND us.user_id = ?
        WHERE qc.category_id IN ($placeholders) AND us.id IS NULL
        ORDER BY RAND()";

    // SQL für bereits beantwortete Fragen, die wiederholt werden sollten
    $sql_repeat = "SELECT q.id, q.question_text, 
                    us.correct_count, 
                    us.incorrect_count,
                    us.view_count,
                    us.last_shown
        FROM questions q
        JOIN question_categories qc ON q.id = qc.question_id
        JOIN user_statistics us ON q.id = us.question_id AND us.user_id = ?
        WHERE qc.category_id IN ($placeholders)
        AND (
            (incorrect_count > correct_count AND DATEDIFF(NOW(), last_shown) >= 1) OR
            (correct_count >= incorrect_count AND DATEDIFF(NOW(), last_shown) >= 7)
            )
        ORDER BY 
            CASE 
                WHEN incorrect_count > correct_count THEN 1
                ELSE 2
            END,
    (incorrect_count - correct_count) DESC,
    DATEDIFF(NOW(), last_shown) DESC,
    RAND()";

    // SQL für alle anderen Fragen (falls nicht genug neue oder zu wiederholende Fragen vorhanden sind)
    $sql_all = "SELECT q.id, q.question_text, 
       COALESCE(us.correct_count, 0) as correct_count, 
       COALESCE(us.incorrect_count, 0) as incorrect_count,
       COALESCE(us.view_count, 0) as view_count,
       COALESCE(us.last_shown, '1970-01-01') as last_shown
        FROM questions q
        JOIN question_categories qc ON q.id = qc.question_id
        LEFT JOIN user_statistics us ON q.id = us.question_id AND us.user_id = ?
        WHERE qc.category_id IN ($placeholders)
        ORDER BY RAND()";

    $questions = [];
    $used_question_ids = [];

    // Neue und unbeantwortete Fragen
    $stmt = $db->prepare($sql_new);
    $params = array_merge([$user_id], $category_ids);
    $stmt->execute($params);
    $new_questions = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($new_questions as $question) {
        if (count($questions) < $limit && !in_array($question['id'], $used_question_ids)) {
            $questions[] = $question;
            $used_question_ids[] = $question['id'];
        }
    }

    if (count($questions) < $limit) {
        // Zu wiederholende Fragen
        $stmt = $db->prepare($sql_repeat);
        $params = array_merge([$user_id], $category_ids);
        $stmt->execute($params);
        $repeat_questions = $stmt->fetchAll(PDO::FETCH_ASSOC);
        foreach ($repeat_questions as $question) {
            if (count($questions) < $limit && !in_array($question['id'], $used_question_ids)) {
                $questions[] = $question;
                $used_question_ids[] = $question['id'];
            }
        }
    }

    if (count($questions) < $limit) {
        // Alle anderen Fragen
        $stmt = $db->prepare($sql_all);
        $params = array_merge([$user_id], $category_ids);
        $stmt->execute($params);
        $all_questions = $stmt->fetchAll(PDO::FETCH_ASSOC);
        foreach ($all_questions as $question) {
            if (count($questions) < $limit && !in_array($question['id'], $used_question_ids)) {
                $questions[] = $question;
                $used_question_ids[] = $question['id'];
            }
        }
    }

    // Mischen Sie die Fragen, um die Reihenfolge zu randomisieren
    shuffle($questions);
    return array_slice($questions, 0, $limit);
    // return $questions;
}

// Hilfefunktion zum Abrufen des Wiederholungsintervalls
function getRepeatIntervalForUser($db, $user_id, $type) {
    $stmt = $db->prepare("SELECT repeat_interval_$type FROM users WHERE id = :user_id");
    $stmt->execute([':user_id' => $user_id]);
    $user_setting = $stmt->fetchColumn();
    if ($user_setting) {
        return $user_setting;
    } else {
        // Falls keine benutzerspezifischen Einstellungen vorhanden sind, globale Einstellungen verwenden
        $stmt = $db->prepare("SELECT value FROM settings WHERE name = ?");
        $stmt->execute(["repeat_interval_$type"]);
        $setting = $stmt->fetchColumn();
        return $setting ? intval($setting) : ($type === 'correct' ? 7 : 1); // Standardwerte falls nicht gesetzt
    }
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

        return $answers;
    } catch (PDOException $e) {
        error_log("Fehler beim Abrufen der Antworten: " . $e->getMessage());
        return [];
    }
}

// Funktion zum Aktualisieren der Benutzerstatistik
function updateUserStatistics($db, $user_id, $question_id, $is_correct) {
    $stmt = $db->prepare("INSERT INTO user_statistics (user_id, question_id, correct_count, incorrect_count, view_count, last_shown) VALUES (?, ?, ?, ?, 1, NOW()) ON DUPLICATE KEY UPDATE correct_count = correct_count + ?, incorrect_count = incorrect_count + ?, view_count = view_count + 1, last_shown = NOW()");
    $correct_increment = $is_correct ? 1 : 0;
    $incorrect_increment = $is_correct ? 0 : 1;
    $stmt->execute([$user_id, $question_id, $correct_increment, $incorrect_increment, $correct_increment, $incorrect_increment]);
}

$categories = getCategories($db, $userId);


if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['start_quiz'])) {
    $selectedCategories = isset($_POST['categories']) ? $_POST['categories'] : [];
    $questionCount = isset($_POST['question_count']) ? intval($_POST['question_count']) : 10;

    if (empty($selectedCategories)) {
        $error = "Bitte wählen Sie mindestens eine Kategorie aus.";
    } else {
        $userCategories = array_column($categories, 'id');
        $invalidCategories = array_diff($selectedCategories, $userCategories);

        if (!empty($invalidCategories)) {
            $error = "Sie haben keine Berechtigung für einige der ausgewählten Kategorien.";
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
                $_SESSION['quiz_started'] = true;
            }
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
    // unset($_SESSION['quiz_questions']);
    // unset($_SESSION['current_question']);
    // unset($_SESSION['correct_answers']);
    // unset($_SESSION['user_answers']);
    // unset($_SESSION['show_result']);
    // unset($_SESSION['confetti']);
    // Alle quizbezogenen Sessionvariablen zurücksetzen
    $quizVariables = ['quiz_questions', 'current_question', 'correct_answers', 'user_answers', 'show_result', 'quiz_started', 'shuffled_answers', 'confetti'];
    foreach ($quizVariables as $var) {
        if (isset($_SESSION[$var])) {
            unset($_SESSION[$var]);
        }
    }
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


        .category-selection {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            margin-bottom: 20px;
        }

        .category-item {
            flex: 0 0 calc(33.333% - 10px);
            max-width: calc(33.333% - 10px);
        }

        .category-checkbox {
            display: none;
        }

        .category-label {
            display: block;
            padding: 10px 15px;
            background-color: #f8f9fa;
            border: 2px solid #dee2e6;
            border-radius: 5px;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .category-checkbox:checked + .category-label {
            background-color: #007bff;
            color: white;
            border-color: #007bff;
        }

        .no-categories {
            width: 100%;
            padding: 15px;
            background-color: #f8d7da;
            border: 1px solid #f5c6cb;
            border-radius: 5px;
            color: #721c24;
        }


        @media (max-width: 576px) {
            .quiz-container {
                padding: 1rem;
            }
            .btn-answer {
                font-size: 1rem;
                padding: 0.8rem;
            }
            .category-item {
               flex: 0 0 calc(50% - 10px);
                max-width: calc(50% - 10px);
            }
        }

        @media (max-width: 768px) {
           .category-item {
                flex: 0 0 calc(50% - 10px);
                max-width: calc(50% - 10px);
            }
        }        


    </style>
</head>
<body>
    <div class="container mt-4 mb-4">
        <div class="quiz-container">
            <?php if ($error): ?>
                <div class="alert alert-danger" role="alert">
                    <i class="fas fa-exclamation-triangle"></i> <?php echo htmlspecialchars($error); ?>
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
                        <?php if (!empty($categories) && is_array($categories)): ?>
                            <div class="form-check category-selection">
                                <?php foreach ($categories as $category): ?>
                                    <input class="form-check-input category-checkbox" type="checkbox" name="categories[]" value="<?php echo $category['id']; ?>" id="category_<?php echo $category['id']; ?>">
                                    <label class="form-check-label category-label" for="category_<?php echo $category['id']; ?>">
                                        <?php echo htmlspecialchars($category['name']); ?>
                                    </label>
                                <?php endforeach; ?>
                            </div>
                    <?php else: ?>
                        <p class="no-categories">Sie haben derzeit keine Kategorien zugewiesen bekommen. Bitte kontaktieren Sie einen Administrator.</p>
                    <?php endif; ?>
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
