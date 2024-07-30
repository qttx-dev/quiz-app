<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
session_start();
require_once '../config.php';

// Überprüfen, ob der Benutzer angemeldet ist und die Rolle Admin oder Manager hat
checkUserRole(ROLE_MANAGER);

// Funktion zum Abrufen aller Kategorien
function getCategories($db) {
    try {
        $stmt = $db->query("SELECT c.id, c.name, COUNT(qc.question_id) as question_count 
                            FROM categories c
                            LEFT JOIN question_categories qc ON c.id = qc.category_id
                            GROUP BY c.id
                            ORDER BY c.name");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Fehler beim Abrufen der Kategorien: " . $e->getMessage());
        return [];
    }
}

// Funktion zum Hinzufügen einer neuen Kategorie
function addCategory($db, $name) {
    try {
        $stmt = $db->prepare("INSERT INTO categories (name) VALUES (?)");
        $stmt->execute([$name]);
        return true;
    } catch (PDOException $e) {
        error_log("Fehler beim Hinzufügen der Kategorie: " . $e->getMessage());
        return false;
    }
}

// Funktion zum Löschen einer Kategorie
function deleteCategory($db, $id) {
    try {
        $db->beginTransaction();

        // Lösche zugehörige Einträge in question_categories
        $stmt = $db->prepare("DELETE FROM question_categories WHERE category_id = ?");
        $stmt->execute([$id]);

        // Lösche die Kategorie selbst
        $stmt = $db->prepare("DELETE FROM categories WHERE id = ?");
        $stmt->execute([$id]);

        $db->commit();
        return true;
    } catch (PDOException $e) {
        $db->rollBack();
        error_log("Fehler beim Löschen der Kategorie: " . $e->getMessage());
        return false;
    }
}

$message = '';

// Verarbeitung des Formulars zum Hinzufügen einer Kategorie
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_category'])) {
    $categoryName = trim($_POST['category_name']);
    if (!empty($categoryName)) {
        if (addCategory($db, $categoryName)) {
            $message = "Kategorie erfolgreich hinzugefügt.";
        } else {
            $message = "Fehler beim Hinzufügen der Kategorie.";
        }
    } else {
        $message = "Bitte geben Sie einen Kategorienamen ein.";
    }
}

// Verarbeitung der Anfrage zum Löschen einer Kategorie
if (isset($_POST['delete_category'])) {
    $categoryId = $_POST['category_id'];
    if (deleteCategory($db, $categoryId)) {
        $message = "Kategorie erfolgreich gelöscht.";
    } else {
        $message = "Fehler beim Löschen der Kategorie.";
    }
}

// Abrufen aller Kategorien
$categories = getCategories($db);
?>

<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kategorien verwalten - Quiz App</title>
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css">
</head>
<body>
    <div class="container mt-5">
        <h1 class="text-center mb-4">Kategorien verwalten</h1>
        
        <?php if ($message): ?>
            <div class="alert alert-info"><?php echo $message; ?></div>
        <?php endif; ?>

        <div class="row">
            <div class="col-md-6">
                <h2>Kategorie hinzufügen</h2>
                <form method="post" action="">
                    <div class="form-group">
                        <label for="category_name">Kategoriename:</label>
                        <input type="text" class="form-control" id="category_name" name="category_name" required>
                    </div>
                    <button type="submit" name="add_category" class="btn btn-primary">
                        <i class="fas fa-plus"></i> Kategorie hinzufügen
                    </button>
                </form>
            </div>
            
            <div class="col-md-6">
                <h2>Vorhandene Kategorien</h2>
                <?php if (!empty($categories)): ?>
                    <ul class="list-group">
                        <?php foreach ($categories as $category): ?>
                            <li class="list-group-item d-flex justify-content-between align-items-center">
                                <?php echo htmlspecialchars($category['name']); ?>
                                <span>
                                    <span class="badge badge-primary badge-pill mr-2">
                                        <?php echo $category['question_count']; ?> Fragen
                                    </span>
                                    <form action="" method="post" class="d-inline" onsubmit="return confirm('Sind Sie sicher, dass Sie diese Kategorie löschen möchten?');">
                                        <input type="hidden" name="category_id" value="<?php echo $category['id']; ?>">
                                        <button type="submit" name="delete_category" class="btn btn-danger btn-sm">
                                            <i class="fas fa-trash"></i> Löschen
                                        </button>
                                    </form>
                                </span>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php else: ?>
                    <p>Keine Kategorien vorhanden.</p>
                <?php endif; ?>
            </div>
        </div>

        <div class="mt-4">
            <a href="../index.php" class="btn btn-secondary">
                <i class="fas fa-arrow-left"></i> Zurück zur Startseite
            </a>
        </div>
    </div>
    <?php include '../footer.php'; ?>
    <script src="https://code.jquery.com/jquery-3.5.1.slim.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.5.3/dist/umd/popper.min.js"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>
</body>
</html>
