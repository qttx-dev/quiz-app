<?php
session_start();
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once '../config.php';

// Überprüfen, ob der Benutzer angemeldet ist und die Rolle Admin oder Manager hat
checkUserRole(ROLE_ADMIN);

function importCSV($db, $file) {
    $error = null;
    if (($handle = fopen($file, "r")) !== FALSE) {
        // Überspringen der Kopfzeile
        fgetcsv($handle, 0, ";");
        
        $db->beginTransaction();
        try {
            while (($data = fgetcsv($handle, 0, ";")) !== FALSE) {
                if (count($data) != 5) {
                    throw new Exception("Ungültiges CSV-Format. Jede Zeile muss 5 Felder haben.");
                }
                
                $question = $data[0];
                $answers = explode("|", $data[1]);
                $correctAnswerIndex = (int)$data[2] - 1;
                $explanation = $data[3];
                $categories = explode(",", $data[4]);
                
                if (count($answers) != 4) {
                    throw new Exception("Jede Frage muss genau 4 Antwortmöglichkeiten haben.");
                }
                
                // Frage einfügen
                $stmt = $db->prepare("INSERT INTO questions (question_text, explanation) VALUES (?, ?)");
                $stmt->execute([$question, $explanation]);
                $questionId = $db->lastInsertId();
                
                // Antworten einfügen
                foreach ($answers as $index => $answer) {
                    $isCorrect = ($index == $correctAnswerIndex) ? 1 : 0;
                    $stmt = $db->prepare("INSERT INTO answers (question_id, answer_text, is_correct) VALUES (?, ?, ?)");
                    $stmt->execute([$questionId, $answer, $isCorrect]);
                }
                
                // Kategorien zuordnen
                foreach ($categories as $categoryId) {
                    $categoryId = trim($categoryId);
                    $stmt = $db->prepare("SELECT id FROM categories WHERE id = ?");
                    $stmt->execute([$categoryId]);
                    if ($stmt->fetch()) {
                        $stmt = $db->prepare("INSERT INTO question_categories (question_id, category_id) VALUES (?, ?)");
                        $stmt->execute([$questionId, $categoryId]);
                    } else {
                        throw new Exception("Ungültige Kategorie-ID: " . $categoryId);
                    }
                }
            }
            $db->commit();
        } catch (Exception $e) {
            $db->rollBack();
            $error = "Fehler beim Import: " . $e->getMessage();
        }
        fclose($handle);
    } else {
        $error = "Konnte die Datei nicht öffnen.";
    }
    return $error;
}

$message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_FILES['csv_file'])) {
        $file = $_FILES['csv_file']['tmp_name'];
        $error = importCSV($db, $file);
        if ($error) {
            $message = $error;
        } else {
            $message = "CSV-Datei erfolgreich importiert.";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Fragen Import</title>
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css">
</head>
<body>
    <div class="container mt-5">
        <h1 class="mb-4">Fragen Import</h1>
        
        <?php if ($message): ?>
            <div class="alert alert-info"><?php echo $message; ?></div>
        <?php endif; ?>

        <div class="card mb-4">
            <div class="card-body">
                <h5 class="card-title">CSV-Datei importieren</h5>
                <form action="" method="post" enctype="multipart/form-data">
                    <div class="form-group">
                        <label for="csv_file">CSV-Datei auswählen:</label>
                        <div class="custom-file">
                            <input type="file" class="custom-file-input" id="csv_file" name="csv_file" accept=".csv" required>
                            <label class="custom-file-label" for="csv_file">Datei auswählen</label>
                        </div>
                    </div>
                    <button type="submit" class="btn btn-primary"><i class="fas fa-file-import"></i> CSV-Datei importieren</button>
                </form>
            </div>
        </div>

        <div class="card">
            <div class="card-body">
                <h5 class="card-title">Anleitung zum Import</h5>
                <ol>
                    <li>Die CSV-Datei muss Semikolons (;) als Trennzeichen verwenden.</li>
                    <li>Die Datei muss UTF-8 kodiert sein, um Umlaute und Sonderzeichen korrekt darzustellen.</li>
                    <li>Die Spalten müssen in dieser Reihenfolge sein: Frage, Antworten, Richtige Antwort (Nummer), Erklärung, Kategorie-IDs</li>
                    <li>Es müssen immer genau 4 Antwortmöglichkeiten angegeben werden, getrennt durch das Pipe-Symbol (|).</li>
                    <li>Die richtige Antwort wird durch ihre Position (1-4) angegeben.</li>
                    <li>Mehrere Kategorie-IDs werden durch Kommas getrennt.</li>
                    <li>Stellen Sie sicher, dass die Kategorie-IDs in der CSV-Datei bereits in der Datenbank existieren.</li>
                </ol>
            </div>
        </div>

        <a href="../index.php" class="btn btn-secondary mt-3"><i class="fas fa-arrow-left"></i> Zurück zur Startseite</a>
    </div>

    <script src="https://code.jquery.com/jquery-3.5.1.slim.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.5.3/dist/umd/popper.min.js"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>
    <script>
        // Aktualisiert den Dateinamen im Label, wenn eine Datei ausgewählt wird
        $('.custom-file-input').on('change', function() {
            var fileName = $(this).val().split('\\').pop();
            $(this).next('.custom-file-label').html(fileName);
        });
    </script>
</body>
</html>
