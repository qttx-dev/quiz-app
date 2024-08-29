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
    <link rel="stylesheet" href="https://cdn.datatables.net/1.10.24/css/jquery.dataTables.min.css"> <!-- DataTables CSS -->
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
            <div class="row">
                <div class="col">
                    <a href="add_question.php" class="btn btn-success btn-block"><i class="fas fa-plus"></i> Neue Frage hinzufügen</a>
                </div>
                <div class="col">
                    <button class="btn btn-warning btn-block" data-toggle="modal" data-target="#categoryModal"><i class="fas fa-tags"></i> Fragen Kategorien zuordnen</button>
                </div>
                <div class="col">
                    <a href="manage_categories.php" class="btn btn-info btn-block"><i class="fas fa-cogs"></i> Kategorien verwalten</a>
                </div>
                <div class="col">
                    <a href="../index.php" class="btn btn-secondary btn-block"><i class="fas fa-arrow-left"></i> Zurück zur Startseite</a>
                </div>
            </div>
        </div>

        <table class="table table-striped" id="questionsTable">
            <thead>
                <tr>
                    <th><input type="checkbox" id="selectAll"></th>
                    <th>ID</th>
                    <th>Frage</th>
                    <th>Kategorien</th>
                    <th>Aktionen</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($questions as $question): ?>
                    <tr>
                        <td><input type="checkbox" class="question-checkbox" value="<?php echo $question['id']; ?>"></td>
                        <td><?php echo $question['id']; ?></td>
                        <td><?php echo htmlspecialchars($question['question_text'], ENT_QUOTES, 'UTF-8'); ?></td>
                        <td><?php echo htmlspecialchars($question['categories'], ENT_QUOTES, 'UTF-8'); ?></td>
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

        <!-- Modal für Kategorien -->
        <div class="modal fade" id="categoryModal" tabindex="-1" role="dialog" aria-labelledby="categoryModalLabel" aria-hidden="true">
            <div class="modal-dialog" role="document">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="categoryModalLabel">Fragen Kategorien zuordnen</h5>
                        <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                            <span aria-hidden="true">&times;</span>
                        </button>
                    </div>
                    <div class="modal-body">
                        <form id="categoryForm" method="post" action="">
                            <div class="form-group">
                                <label for="selectedCategories">Wählen Sie Kategorien:</label>
                                <?php foreach ($categories as $category): ?>
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" name="category_ids[]" value="<?php echo $category['id']; ?>" id="category_<?php echo $category['id']; ?>">
                                        <label class="form-check-label" for="category_<?php echo $category['id']; ?>">
                                            <?php echo htmlspecialchars($category['name']); ?>
                                        </label>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                            <input type="hidden" name="question_ids" id="question_ids" value="">
                        </form>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-dismiss="modal">Abbrechen</button>
                        <button type="button" class="btn btn-primary" id="saveCategories">Speichern</button>
                    </div>
                </div>
            </div>
        </div>

    </div>
    <?php include '../footer.php'; ?>
    <script src="https://code.jquery.com/jquery-3.5.1.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.5.3/dist/umd/popper.min.js"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>
    <script src="https://cdn.datatables.net/1.10.24/js/jquery.dataTables.min.js"></script>
    <script>
    $(document).ready(function() {
        $('#questionsTable').DataTable({
            language: {
                "sProcessing":   "Wird bearbeitet...",
                "sLengthMenu":   "Zeige _MENU_ Einträge",
                "sZeroRecords":  "Keine Einträge vorhanden.",
                "sInfo":         "Zeige von _START_ bis _END_ von _TOTAL_ Einträgen",
                "sInfoEmpty":    "Zeige 0 bis 0 von 0 Einträgen",
                "sInfoFiltered": "(gefiltert von _MAX_ insgesamt Einträgen)",
                "sSearch":       "Suche:",
                "sEmptyTable":   "Keine Daten verfügbar in der Tabelle",
                "oPaginate": {
                    "sFirst":    "Erste",
                    "sLast":     "Letzte",
                    "sNext":     "Nächste",
                    "sPrevious": "Vorherige"
                }
            }
        });

        // Checkboxen für die Auswahl der Fragen
        $('#selectAll').click(function() {
            $('.question-checkbox').prop('checked', this.checked);
        });

        // Speichern der ausgewählten Fragen und Kategorien
        $('#saveCategories').click(function() {
            var selectedQuestions = [];
            $('.question-checkbox:checked').each(function() {
                selectedQuestions.push($(this).val());
            });
            $('#question_ids').val(selectedQuestions.join(','));

            if (selectedQuestions.length === 0) {
                alert('Bitte wählen Sie mindestens eine Frage aus.');
                return;
            }

            $('#categoryForm').submit(); // Formular absenden
        });
    });
    </script>
</body>
</html>