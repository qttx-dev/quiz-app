<?php
session_start();
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once '../config.php';

// Überprüfen, ob der Benutzer angemeldet ist und die Rolle Admin oder Manager hat
checkUserRole(ROLE_ADMIN);

function getCategories($db) {
    try {
        $stmt = $db->query("SELECT id, name FROM categories ORDER BY name");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Fehler beim Abrufen der Kategorien: " . $e->getMessage());
        return [];
    }
}

function getAllQuestionsForExport($db, $categoryIds) {
    try {
        $placeholders = implode(',', array_fill(0, count($categoryIds), '?'));
        $sql = "
            SELECT q.id, q.question_text, q.explanation, 
                   GROUP_CONCAT(DISTINCT qc.category_id ORDER BY qc.category_id ASC SEPARATOR ',') AS category_ids,
                   GROUP_CONCAT(DISTINCT a.answer_text ORDER BY a.id ASC SEPARATOR '|') AS answers,
                   (SELECT answer_text FROM answers WHERE question_id = q.id AND is_correct = 1 LIMIT 1) AS correct_answer
            FROM questions q 
            JOIN question_categories qc ON q.id = qc.question_id
            LEFT JOIN answers a ON q.id = a.question_id
            WHERE qc.category_id IN ($placeholders)
            GROUP BY q.id
            ORDER BY q.id
        ";
        $stmt = $db->prepare($sql);
        $stmt->execute($categoryIds);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Fehler beim Abrufen der Fragen: " . $e->getMessage());
        return [];
    }
}

function exportCSV($questions) {
    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename=questions_export.csv');
    
    echo "\xEF\xBB\xBF"; // BOM für UTF-8

    $output = fopen('php://output', 'w');

    fputcsv($output, ['Frage', 'Antworten', 'Richtige Antwort', 'Erklärung', 'Kategorie-IDs'], ';');

    foreach ($questions as $question) {
        $answers = explode('|', $question['answers']);
        
        while (count($answers) < 4) {
            $answers[] = '';
        }
        
        $correctAnswerIndex = array_search($question['correct_answer'], $answers) + 1;
        
        $row = [
            $question['question_text'],
            implode('|', $answers),
            $correctAnswerIndex,
            $question['explanation'],
            $question['category_ids']
        ];
        
        fputcsv($output, $row, ';');
    }

    fclose($output);
    exit;
}

$categories = getCategories($db);
$message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['export']) && !empty($_POST['categories'])) {
        $selectedCategories = $_POST['categories'];
        $questions = getAllQuestionsForExport($db, $selectedCategories);
        if ($questions) {
            exportCSV($questions);
        } else {
            $message = "Keine Fragen für die ausgewählten Kategorien gefunden.";
        }
    } elseif (isset($_POST['export']) && empty($_POST['categories'])) {
        $message = "Bitte wählen Sie mindestens eine Kategorie aus.";
    }
}
?>

<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Fragen Export</title>
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css">
</head>
<body>
    <div class="container mt-5">
        <h1 class="mb-4">Fragen Export</h1>
        
        <?php if ($message): ?>
            <div class="alert alert-info"><?php echo $message; ?></div>
        <?php endif; ?>

        <div class="card mb-4">
            <div class="card-body">
                <h5 class="card-title">Kategorien auswählen</h5>
                <form action="" method="post">
                    <div class="form-group">
                        <div class="row">
                            <?php foreach ($categories as $category): ?>
                                <div class="col-md-4 mb-2">
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" name="categories[]" id="category<?php echo $category['id']; ?>" value="<?php echo $category['id']; ?>">
                                        <label class="form-check-label" for="category<?php echo $category['id']; ?>">
                                            <?php echo htmlspecialchars($category['name']); ?>
                                        </label>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <button type="submit" name="export" class="btn btn-primary"><i class="fas fa-file-export"></i> Ausgewählte Fragen exportieren</button>
                </form>
            </div>
        </div>

        <div class="card">
            <div class="card-body">
                <h5 class="card-title">Anleitung zum Export</h5>
                <ol>
                    <li>Wählen Sie eine oder mehrere Kategorien aus, deren Fragen Sie exportieren möchten.</li>
                    <li>Die exportierte CSV-Datei verwendet Semikolons (;) als Trennzeichen.</li>
                    <li>Die Datei ist UTF-8 kodiert, um Umlaute und Sonderzeichen korrekt darzustellen.</li>
                    <li>Beim Öffnen in Excel: Wählen Sie "Daten" > "Aus Text/CSV" und stellen Sie sicher, dass Sie "65001: Unicode (UTF-8)" als Dateiursprung auswählen.</li>
                    <li>Die Spalten sind: Frage, Antworten, Richtige Antwort (Nummer), Erklärung, Kategorie-IDs</li>
                    <li>Es gibt immer genau 4 Antwortmöglichkeiten, getrennt durch das Pipe-Symbol (|).</li>
                    <li>Mehrere Kategorie-IDs werden durch Kommas getrennt.</li>
                </ol>
            </div>
        </div>

        <a href="../index.php" class="btn btn-secondary mt-3"><i class="fas fa-arrow-left"></i> Zurück zur Startseite</a>
    </div>

    <script src="https://code.jquery.com/jquery-3.5.1.slim.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.5.3/dist/umd/popper.min.js"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>
</body>
</html>
