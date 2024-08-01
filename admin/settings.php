<?php
session_start();
require_once '../config.php'; // Pfad zu config.php anpassen

// Überprüfen, ob der Benutzer angemeldet ist und die Rolle Admin hat
checkUserRole(ROLE_ADMIN);

$message = '';

// Überprüfen, ob eine Einstellung aktualisiert wurde
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $allowSelfRegistration = isset($_POST['allow_self_registration']) ? 1 : 0;

    $stmt = $db->prepare("UPDATE settings SET value = ? WHERE name = 'allow_self_registration'");
    if ($stmt->execute([$allowSelfRegistration])) {
        $message = "Einstellungen erfolgreich aktualisiert.";
    } else {
        $message = "Fehler beim Aktualisieren der Einstellungen.";
    }
}

// Abrufen der aktuellen Einstellungen
$stmt = $db->query("SELECT value FROM settings WHERE name = 'allow_self_registration'");
$currentSetting = $stmt->fetchColumn();

?>

<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Einstellungen - Quiz App</title>
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
</head>
<body>
    <div class="container mt-5">
        <h1 class="mb-4">Einstellungen</h1>
        
        <?php if ($message): ?>
            <div class="alert alert-info"><?php echo $message; ?></div>
        <?php endif; ?>

        <form method="post">
            <div class="form-group">
                <label for="allow_self_registration">Selbstregistrierung aktivieren:</label>
                <input type="checkbox" id="allow_self_registration" name="allow_self_registration" value="1" <?php echo $currentSetting == '1' ? 'checked' : ''; ?>>
            </div>
            <button type="submit" class="btn btn-primary">Einstellungen speichern</button>
            <a href="db/export.php" class="btn btn-primary mt-3">Datenbank exportieren</a>
            <a href="db/import.php" class="btn btn-primary mt-3">Datenbank importieren</a>
        </form>

        <a href="../index.php" class="btn btn-secondary mt-3"><i class="fas fa-arrow-left"></i> Zurück zur Startseite</a>
    </div>
    <?php include '../footer.php'; ?>
    <script src="https://code.jquery.com/jquery-3.5.1.slim.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.5.3/dist/umd/popper.min.js"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>
</body>
</html>
