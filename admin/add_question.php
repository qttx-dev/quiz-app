<?php
session_start();
require_once '../config.php';

// Überprüfen, ob der Benutzer angemeldet und berechtigt ist
checkUserRole(ROLE_ADMIN);

$message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $question_text = $_POST['question_text'];
    $explanation = $_POST['explanation'];
    $category_ids = $_POST['category_id']; // Jetzt ein Array
    $answers = $_POST['answers'];
    $correct_answer = $_POST['correct_answer'];

    try {
        $db = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME, DB_USER, DB_PASS);
        $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        $db->beginTransaction();

        // Frage einfügen
        $stmt = $db->prepare("INSERT INTO questions (question_text, explanation) VALUES (?, ?)");
        $stmt->execute([$question_text, $explanation]);
        $question_id = $db->lastInsertId();

        // Kategorien zuweisen
        $stmt = $db->prepare("INSERT INTO question_categories (question_id, category_id) VALUES (?, ?)");
        foreach ($category_ids as $category_id) {
            $stmt->execute([$question_id, $category_id]);
        }

        // Antworten einfügen
        foreach ($answers as $index => $answer_text) {
            $is_correct = ($index == $correct_answer) ? 1 : 0;
            $stmt = $db->prepare("INSERT INTO answers (question_id, answer_text, is_correct) VALUES (?, ?, ?)");
            $stmt->execute([$question_id, $answer_text, $is_correct]);
        }

        $db->commit();
        $message = "Frage erfolgreich hinzugefügt.";
    } catch (PDOException $e) {
        $db->rollBack();
        $message = "Fehler beim Hinzufügen der Frage: " . $e->getMessage();
    }
}

try {
    $db = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME, DB_USER, DB_PASS);
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $stmt = $db->query("SELECT * FROM categories");
    $categories = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    die("Fehler bei der Datenbankverbindung: " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Frage hinzufügen - Quiz App</title>
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
    <style>
        select[multiple] {
            height: 150px;
        }
    </style>
</head>
<body>
    <div class="container mt-5">
        <h1 class="text-center mb-4">Frage hinzufügen</h1>
        
        <?php if ($message): ?>
            <div class="alert alert-info"><?php echo $message; ?></div>
        <?php endif; ?>

        <form method="post" action="">
            <div class="form-group">
                <label for="question_text">Fragetext:</label>
                <textarea class="form-control" id="question_text" name="question_text" required></textarea>
            </div>
            <div class="form-group">
                <label for="explanation">Erklärung:</label>
                <textarea class="form-control" id="explanation" name="explanation"></textarea>
            </div>
            <div class="form-group">
                <label for="category_id">Kategorien:</label>
                <select class="form-control" id="category_id" name="category_id[]" multiple required>
                    <?php foreach ($categories as $category): ?>
                        <option value="<?php echo $category['id']; ?>"><?php echo $category['name']; ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label>Antworten:</label>
                <div id="answers">
                    <div class="input-group mb-3">
                        <input type="text" class="form-control" name="answers[]" required>
                        <div class="input-group-append">
                            <div class="input-group-text">
                                <input type="radio" name="correct_answer" value="0" required>
                            </div>
                        </div>
                    </div>
                </div>
                <button type="button" class="btn btn-secondary" onclick="addAnswer()">Antwort hinzufügen</button>
                <small class="form-text text-muted mt-2">
                    Hinweis für die Eingabe von Fragen mit <b>mehreren</b> richtigen Antworten:<br>
                    Für die korrekte Darstellung müssen Sie genau das untenstehende Format übernehmen. Die Anzahl der Listenpunkte &lt;li&gt; ist nicht relevant!
                    <br><br>
                    <u>Beispiel</u><br><br>
                    <code>
                        Wodurch ist eine Pandemie gekennzeichnet?<code>&lt;br&gt;</code><br>
                        <code>&lt;br&gt;</code><br>
                        &lt;ul&gt;<br>
                        &lt;li&gt;A. Die Krankheit breitet sich über Länder und Kontinente hinweg aus&lt;/li&gt;<br>
                        &lt;li&gt;B. Die Krankheit tritt nur in einer Region der Erde auf&lt;/li&gt;<br>
                        &lt;li&gt;C. Es erkranken sehr viele Menschen in Zentralafrika an dieser Infektion&lt;/li&gt;<br>
                        &lt;li&gt;D. Die Zahl der Erkrankungen in einer bestimmten Region steigt über das normal zu erwartende (endemische) Level an&lt;/li&gt;<br>
                        &lt;li&gt;E. Pandemien werden meist von neu auftretenden Erregern oder Virustypen verursacht&lt;/li&gt;<br>
                        &lt;/ul&gt;
                    </code>
                    <br>
                    <br>
                    Stellen Sie sicher, dass Sie <code>&lt;br&gt;</code> für Zeilenumbrüche verwenden, eine Leerzeile und die Antworten in einer ungeordneten Liste (<code>&lt;ul&gt;</code>) formatieren.
                </small>
            </div>
            <div class="row mt-4">
    <div class="col">
        <button type="submit" class="btn btn-primary btn-block">Frage speichern</button>
    </div>
    <div class="col">
        <a href="manage_questions.php" class="btn btn-secondary btn-block"><i class="fas fa-arrow-left"></i> Zurück zur Fragenübersicht</a>
    </div>
</div>
    </div>

    <script src="https://code.jquery.com/jquery-3.5.1.slim.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.5.3/dist/umd/popper.min.js"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
    <script>
        function addAnswer() {
            const answersDiv = document.getElementById('answers');
            const answerCount = answersDiv.children.length;
            const newAnswer = document.createElement('div');
            newAnswer.classList.add('input-group', 'mb-3');
            newAnswer.innerHTML = `
                <input type="text" class="form-control" name="answers[]" required>
                <div class="input-group-append">
                    <div class="input-group-text">
                        <input type="radio" name="correct_answer" value="${answerCount}" required>
                    </div>
                </div>
            `;
            answersDiv.appendChild(newAnswer);
        }

        $(document).ready(function() {
            $('#category_id').select2({
                placeholder: 'Wählen Sie eine oder mehrere Kategorien',
                allowClear: true
            });
        });
    </script>

    <footer class="footer mt-5">
        <div class="container text-center">
            <p>Entwickelt mit ❤️ für interaktives Lernen und Wissensüberprüfung</p>
        </div>
    </footer>
</body>
</html>