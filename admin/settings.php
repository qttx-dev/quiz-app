<?php
session_start();
require_once '../config.php';

checkUserRole(ROLE_ADMIN);

$message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $allowSelfRegistration = isset($_POST['allow_self_registration']) ? 1 : 0;
    $repeatIntervalCorrect = $_POST['repeat_interval_correct'];
    $repeatIntervalIncorrect = $_POST['repeat_interval_incorrect'];

    $stmt = $db->prepare("UPDATE settings SET value = ? WHERE name = 'allow_self_registration'");
    $stmt->execute([$allowSelfRegistration]);

    $stmt = $db->prepare("UPDATE settings SET value = ? WHERE name = 'repeat_interval_correct'");
    $stmt->execute([$repeatIntervalCorrect]);

    $stmt = $db->prepare("UPDATE settings SET value = ? WHERE name = 'repeat_interval_incorrect'");
    $stmt->execute([$repeatIntervalIncorrect]);

    $message = "Einstellungen erfolgreich aktualisiert.";
}

$stmt = $db->query("SELECT name, value FROM settings WHERE name IN ('allow_self_registration', 'repeat_interval_correct', 'repeat_interval_incorrect')");
$settings = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);

// Sicherstellen, dass die Indizes existieren
$allowSelfRegistration = isset($settings['allow_self_registration']) ? $settings['allow_self_registration'] : 0;
$repeatIntervalCorrect = isset($settings['repeat_interval_correct']) ? $settings['repeat_interval_correct'] : '';
$repeatIntervalIncorrect = isset($settings['repeat_interval_incorrect']) ? $settings['repeat_interval_incorrect'] : '';
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
                        <input type="checkbox" class="custom-control-input" id="allow_self_registration" name="allow_self_registration" value="1" <?php echo $allowSelfRegistration == '1' ? 'checked' : ''; ?>>
                        <label class="custom-control-label" for="allow_self_registration">Selbstregistrierung aktivieren</label>
                    </div>
                </div>
                <div class="form-group">
                    <label for="repeat_interval_correct">Wiederholungsintervall bei korrekter Antwort (in Tagen)</label>
                    <input type="number" class="form-control" id="repeat_interval_correct" name="repeat_interval_correct" value="<?php echo htmlspecialchars($repeatIntervalCorrect); ?>" required>
                </div>
                <div class="form-group">
                    <label for="repeat_interval_incorrect">Wiederholungsintervall bei falscher Antwort (in Tagen)</label>
                    <input type="number" class="form-control" id="repeat_interval_incorrect" name="repeat_interval_incorrect" value="<?php echo htmlspecialchars($repeatIntervalIncorrect); ?>" required>
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
            <div class="mt-8">
                <a href="test_mail.php" class="btn btn-secondary btn-block mt-2">
                <i class="fas fa-envelope"></i> E-Mail Einstellungen testen
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
