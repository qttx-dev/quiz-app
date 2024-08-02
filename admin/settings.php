<?php
session_start();
require_once '../config.php';

checkUserRole(ROLE_ADMIN);

$message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $allowSelfRegistration = isset($_POST['allow_self_registration']) ? 1 : 0;

    $stmt = $db->prepare("UPDATE settings SET value = ? WHERE name = 'allow_self_registration'");
    if ($stmt->execute([$allowSelfRegistration])) {
        $message = "Einstellungen erfolgreich aktualisiert.";
    } else {
        $message = "Fehler beim Aktualisieren der Einstellungen.";
    }
}

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
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css">
    <style>
        body {
            background-color: #f8f9fa;
        }
        .settings-container {
            background-color: #ffffff;
            border-radius: 15px;
            box-shadow: 0 0.5rem 1rem rgba(0, 0, 0, 0.15);
            padding: 2rem;
            margin-top: 2rem;
        }
        .custom-control-input:checked ~ .custom-control-label::before {
            background-color: #28a745;
            border-color: #28a745;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="settings-container">
            <h1 class="mb-4 text-center">
                <i class="fas fa-cogs"></i> Einstellungen
            </h1>
            
            <?php if ($message): ?>
                <div class="alert alert-info">
                    <i class="fas fa-info-circle"></i> <?php echo $message; ?>
                </div>
            <?php endif; ?>

            <form method="post">
                <div class="form-group">
                    <div class="custom-control custom-switch">
                        <input type="checkbox" class="custom-control-input" id="allow_self_registration" name="allow_self_registration" value="1" <?php echo $currentSetting == '1' ? 'checked' : ''; ?>>
                        <label class="custom-control-label" for="allow_self_registration">Selbstregistrierung aktivieren</label>
                    </div>
                </div>
                <button type="submit" class="btn btn-primary btn-block">
                    <i class="fas fa-save"></i> Einstellungen speichern
                </button>
            </form>
            <hr>
            <div class="mt-8">
                <a href="db/debug.php" class="btn btn-secondary btn-block mt-2">
                    <i class="fas fa-database"></i> Datenbank debuggen
                </a>
                <a href="db/export.php" class="btn btn-secondary btn-block">
                    <i class="fas fa-file-export"></i> Datenbank exportieren
                </a>
                <a href="db/import.php" class="btn btn-secondary btn-block mt-2">
                    <i class="fas fa-file-import"></i> Datenbank importieren
                </a>
            </div>
            <hr>
            <a href="../index.php" class="btn btn-secondary btn-block mt-4">
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
