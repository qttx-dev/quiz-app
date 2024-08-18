<?php
session_start();
require_once '../config.php';

checkUserRole(ROLE_ADMIN);

try {
    // Überprüfen, ob die Tabelle user_categories bereits existiert
    $stmt = $db->query("SHOW TABLES LIKE 'user_categories'");
    $tableExists = $stmt->rowCount() > 0;

    if (!$tableExists) {
        // Tabelle user_categories erstellen
        $createTableSQL = "
            CREATE TABLE user_categories (
                id INT AUTO_INCREMENT PRIMARY KEY,
                user_id INT NOT NULL,
                category_id INT NOT NULL,
                FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
                FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE CASCADE,
                UNIQUE KEY unique_user_category (user_id, category_id)
            )
        ";
        $db->exec($createTableSQL);
        $message = "Tabelle user_categories erfolgreich erstellt.";
    } else {
        $message = "Tabelle user_categories existiert bereits.";
    }
} catch (PDOException $e) {
    $message = "Fehler beim Erstellen der Tabelle: " . $e->getMessage();
}

?>

<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Update - Quiz App</title>
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css">
</head>
<body>
    <div class="container mt-5">
        <h1 class="mb-4">Update</h1>
        <div class="alert alert-info">
            <?php echo $message; ?>
        </div>
        <a href="user_management.php" class="btn btn-primary">Zurück zur Benutzerverwaltung</a>
    </div>

    <?php include '../footer.php'; ?>

    <script src="https://code.jquery.com/jquery-3.5.1.slim.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.5.3/dist/umd/popper.min.js"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>
</body>
</html>
