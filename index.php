<?php
session_start();
require_once 'config.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

$username = $_SESSION['username'];
$userRole = $_SESSION['user_role'];
?>

<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Quiz App</title>
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css">
    <link rel="stylesheet" href="styles.css">
</head>
<body>
    <div class="container mt-5 text-center">
    <i class="fas fa-user-graduate welcome-icon"></i>
    <h1 class="text-center mb-4">Willkommen zur<br>Quiz App</h1>
    <?php if (isset($_SESSION['message'])) {
    echo "<div class='alert alert-success mt-3 mb-3'>" . $_SESSION['message'] . "</div>";
    unset($_SESSION['message']); // Nachricht nach dem Anzeigen löschen
} ?>
        <a href="quiz.php" class="btn btn-primary btn-custom-welcome"><i class="fas fa-play"></i> Quiz starten</a>
        <form action="reset.php" method="post">
            <button type="submit" class="btn btn-warning btn-custom-welcome mt-3"><i class="fas fa-chart-bar"></i> Fragenstatistik zurücksetzen</button>
        </form>
        <?php if ($userRole === ROLE_ADMIN || $userRole === ROLE_EDITOR): ?>
        <div class="card admin-buttons mt-4">
            <div class="card-body">
                <h2 class="card-title">Verwaltung</h2>
                <a href="admin/manage_questions.php" class="btn btn-secondary btn-custom"><i class="fas fa-tasks"></i> Fragen verwalten</a>
                <a href="admin/manage_categories.php" class="btn btn-secondary btn-custom"><i class="fas fa-list"></i> Kategorien verwalten</a>
                <?php endif; ?>
                <?php if ($userRole === ROLE_ADMIN || $userRole === ROLE_MANAGER): ?>
                    <a href="admin/export_questions.php" class="btn btn-secondary btn-custom"><i class="fas fa-file-export"></i> Fragen exportieren</a>
                    <a href="admin/import_questions.php" class="btn btn-secondary btn-custom"><i class="fas fa-file-import"></i> Fragen importieren</a>
                <?php endif; ?>
                <?php if ($userRole === ROLE_ADMIN): ?>
                    <a href="admin/user_management.php" class="btn btn-secondary btn-custom"><i class="fas fa-users-cog"></i> Benutzerverwaltung</a>
                    <a href="admin/settings.php" class="btn btn-secondary btn-custom"><i class="fas fa-cogs"></i> Einstellungen</a>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <?php include 'footer.php'; ?>

    <script src="https://code.jquery.com/jquery-3.5.1.slim.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.5.3/dist/umd/popper.min.js"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>
</body>
</html>
