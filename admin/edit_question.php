<?php
session_start();
require_once '../config.php';

// Überprüfen, ob der Benutzer angemeldet ist und die Rolle Admin oder Manager hat
checkUserRole(ROLE_MANAGER);

// Funktion zum Abrufen einer Frage
function getQuestion($db, $id) {
    try {
        $stmt = $db->prepare("SELECT q.*, GROUP_CONCAT(qc.category_id) as category_ids 
                              FROM questions q 
                              LEFT JOIN question_categories qc ON q.id = qc.question_id 
                              WHERE q.id = ?
                              GROUP BY q.id");
        $stmt->execute([$id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Fehler beim Abrufen der Frage: " . $e->getMessage());
        return null;
    }
}

// Funktion zum Abrufen der Antworten einer Frage
function getAnswers($db, $questionId) {
    try {
        $stmt = $db->prepare("SELECT * FROM answers WHERE question_id = ? ORDER BY id");
        $stmt->execute([$questionId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Fehler beim Abrufen der Antworten: " . $e->getMessage());
        return [];
    }
}

// Funktion zum Aktualisieren einer Frage
function updateQuestion($db, $id, $questionText, $explanation, $categoryIds) {
    try {
        $db->beginTransaction();

        // Update question text and explanation
        $stmt = $db->prepare("UPDATE questions SET question_text = ?, explanation = ? WHERE id = ?");
        $stmt->execute([$questionText, $explanation, $id]);

        // Delete old category associations
        $stmt = $db->prepare("DELETE FROM question_categories WHERE question_id = ?");
        $stmt->execute([$id]);

        // Insert new category associations
        $stmt = $db->prepare("INSERT INTO question_categories (question_id, category_id) VALUES (?, ?)");
        foreach ($categoryIds as $categoryId) {
            $stmt->execute([$id, $categoryId]);
        }

        $db->commit();
        return true;
    } catch (PDOException $e) {
        $db->rollBack();
        error_log("Fehler beim Aktualisieren der Frage: " . $e->getMessage());
        return false;
    }
}

// Funktion zum Aktualisieren der Antworten
function updateAnswers($db, $questionId, $answerTexts, $correctAnswerId) {
    try {
        $db->beginTransaction();

        $stmt = $db->prepare("UPDATE answers SET answer_text = ?, is_correct = ? WHERE id = ?");
        foreach ($answerTexts as $answerId => $answerText) {
            $isCorrect = ($answerId == $correctAnswerId) ? 1 : 0;
            $stmt->execute([$answerText, $isCorrect, $answerId]);
        }

        $db->commit();
        return true;
    } catch (PDOException $e) {
        $db->rollBack();
        error_log("Fehler beim Aktualisieren der Antworten: " . $e->getMessage());
        return false;
    }
}

// Abrufen aller Kategorien für das Dropdown-Menü
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
$message = '';

// Abrufen der Frage, die bearbeitet werden soll
if (isset($_GET['id'])) {
    $questionId = intval($_GET['id']);
    $question = getQuestion($db, $questionId);
    $answers = getAnswers($db, $questionId);
} else {
    die("Frage nicht angegeben.");
}

// Verarbeitung des Bearbeitungsformulars
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $questionText = $_POST['question_text'];
    $explanation = $_POST['explanation'];
    $categoryIds = $_POST['category_ids'];
    $answerTexts = $_POST['answer_text'];
    $correctAnswerId = $_POST['correct_answer'];

    if (updateQuestion($db, $questionId, $questionText, $explanation, $categoryIds) &&
        updateAnswers($db, $questionId, $answerTexts, $correctAnswerId)) {
        $message = "Frage erfolgreich aktualisiert.";
        $question = getQuestion($db, $questionId);
        $answers = getAnswers($db, $questionId);
    } else {
        $message = "Fehler beim Aktualisieren der Frage.";
    }
}

?>

<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Frage bearbeiten - Quiz App</title>
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css">
</head>
<body>
    <div class="container mt-5">
        <h1 class="text-center mb-4">Frage bearbeiten</h1>

        <?php if ($message): ?>
            <div class="alert alert-info"><?php echo $message; ?></div>
        <?php endif; ?>

        <form method="post" action="">
            <div class="form-group">
                <label for="question_text">Frage:</label>
                <textarea class="form-control" id="question_text" name="question_text" rows="3" required><?php echo htmlspecialchars($question['question_text']); ?></textarea>
            </div>
            <div class="form-group">
                <label for="explanation">Erklärung:</label>
                <textarea class="form-control" id="explanation" name="explanation" rows="3"><?php echo htmlspecialchars($question['explanation']); ?></textarea>
            </div>
            <div class="form-group">
                <label>Kategorien:</label>
                <div>
                    <?php 
                    $selectedCategories = explode(',', $question['category_ids']);
                    foreach ($categories as $category): 
                    ?>
                        <div class="form-check form-check-inline">
                            <input class="form-check-input" type="checkbox" name="category_ids[]" id="category_<?php echo $category['id']; ?>" value="<?php echo $category['id']; ?>" <?php echo in_array($category['id'], $selectedCategories) ? 'checked' : ''; ?>>
                            <label class="form-check-label" for="category_<?php echo $category['id']; ?>"><?php echo htmlspecialchars($category['name']); ?></label>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <h4>Antwortmöglichkeiten:</h4>
            <?php foreach ($answers as $answer): ?>
                <div class="form-group">
                    <div class="input-group">
                        <input type="text" name="answer_text[<?php echo $answer['id']; ?>]" value="<?php echo htmlspecialchars($answer['answer_text']); ?>" class="form-control" required>
                        <div class="input-group-append">
                            <div class="input-group-text">
                                <input type="radio" name="correct_answer" value="<?php echo $answer['id']; ?>" <?php echo $answer['is_correct'] ? 'checked' : ''; ?> required>
                                <label class="mb-0 ml-2">Richtig</label>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
            <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Frage aktualisieren</button>
        </form>

        <div class="mt-4">
            <a href="manage_questions.php" class="btn btn-secondary"><i class="fas fa-arrow-left"></i> Zurück zur Fragenübersicht</a>
        </div>
    </div>
    <?php include '../footer.php'; ?>
    <script src="https://code.jquery.com/jquery-3.5.1.slim.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.5.3/dist/umd/popper.min.js"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>
</body>
</html>
