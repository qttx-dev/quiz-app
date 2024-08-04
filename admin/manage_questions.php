<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
session_start();
require_once '../config.php';

// Überprüfen, ob der Benutzer angemeldet ist und die Rolle Admin oder Manager hat
checkUserRole(ROLE_MANAGER);

// Funktion zum Abrufen aller Fragen mit Kategorien und richtiger Antwort
function getAllQuestions($db) {
    try {
        $stmt = $db->query("
            SELECT q.id, q.question_text, 
                   GROUP_CONCAT(DISTINCT c.name ORDER BY c.name ASC SEPARATOR ', ') AS categories,
                   (SELECT answer_text FROM answers WHERE question_id = q.id AND is_correct = 1 LIMIT 1) AS correct_answer
            FROM questions q 
            LEFT JOIN question_categories qc ON q.id = qc.question_id
            LEFT JOIN categories c ON qc.category_id = c.id
            GROUP BY q.id
            ORDER BY q.id DESC
        ");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Fehler beim Abrufen der Fragen: " . $e->getMessage());
        return [];
    }
}

// Funktion zum Löschen einer Frage
function deleteQuestion($db, $questionId) {
    try {
        $db->beginTransaction();

        // Lösche zugehörige Einträge in question_categories
        $stmt = $db->prepare("DELETE FROM question_categories WHERE question_id = ?");
        $stmt->execute([$questionId]);

        // Lösche zugehörige Antworten
        $stmt = $db->prepare("DELETE FROM answers WHERE question_id = ?");
        $stmt->execute([$questionId]);

        // Lösche zugehörige Einträge in user_statistics (falls vorhanden)
        $stmt = $db->prepare("DELETE FROM user_statistics WHERE question_id = ?");
        $stmt->execute([$questionId]);

        // Lösche die Frage selbst
        $stmt = $db->prepare("DELETE FROM questions WHERE id = ?");
        $stmt->execute([$questionId]);

        $db->commit();
        return true;
    } catch (PDOException $e) {
        $db->rollBack();
        error_log("Fehler beim Löschen der Frage: " . $e->getMessage());
        throw new Exception("Fehler beim Löschen der Frage: " . $e->getMessage());
    }
}


$message = '';
$questions = getAllQuestions($db);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete'])) {
    $questionId = $_POST['question_id'];
    if (deleteQuestion($db, $questionId)) {
        $message = "Frage erfolgreich gelöscht.";
        $questions = getAllQuestions($db); // Aktualisiere die Fragenliste
    } else {
        $message = "Fehler beim Löschen der Frage.";
    }
}

?>

<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Fragen verwalten</title>
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css">
    <style>
        .table td {
            vertical-align: middle;
        }
    </style>
</head>
<body>
    <div class="container mt-5">
        <h1 class="mb-4">Fragen verwalten</h1>
        
        <?php if ($message): ?>
            <div class="alert alert-info"><?php echo $message; ?></div>
        <?php endif; ?>

        <div class="mb-3">
            <a href="add_question.php" class="btn btn-success"><i class="fas fa-plus"></i> Neue Frage hinzufügen</a>
        </div>

        <table class="table table-striped">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Frage</th>
                    <th>Richtige Antwort</th>
                    <th>Kategorien</th>
                    <th>Aktionen</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($questions as $question): ?>
                    <tr>
                        <td><?php echo $question['id']; ?></td>
                        <td><?php echo htmlspecialchars($question['question_text']); ?></td>
                        <td><?php echo htmlspecialchars($question['correct_answer']); ?></td>
                        <td><?php echo htmlspecialchars($question['categories']); ?></td>
                        <td>
                            <a href="edit_question.php?id=<?php echo $question['id']; ?>" class="btn btn-primary btn-sm btn-action"><i class="fas fa-edit"></i> Bearbeiten</a>
                            <form action="" method="post" class="d-inline" onsubmit="return confirm('Sind Sie sicher, dass Sie diese Frage löschen möchten?');">
                                <input type="hidden" name="question_id" value="<?php echo $question['id']; ?>">
                                <button type="submit" name="delete" class="btn btn-danger btn-sm btn-action"><i class="fas fa-trash"></i> Löschen</button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <a href="../index.php" class="btn btn-secondary mt-3"><i class="fas fa-arrow-left"></i> Zurück zur Startseite</a>
    </div>
    <?php include '../footer.php'; ?>
    <script src="https://code.jquery.com/jquery-3.5.1.slim.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.5.3/dist/umd/popper.min.js"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>
</body>
</html>
