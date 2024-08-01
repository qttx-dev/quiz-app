<?php
session_start();
require_once 'config.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

$username = $_SESSION['username'];
$userRole = $_SESSION['user_role'];

// Überprüfen, ob Statistiken für den Benutzer vorhanden sind
function hasUserStatistics($db, $userId) {
    $stmt = $db->prepare("SELECT COUNT(*) FROM user_statistics WHERE user_id = ?");
    $stmt->execute([$userId]);
    return $stmt->fetchColumn() > 0;
}

$hasStats = hasUserStatistics($db, $_SESSION['user_id']);
?>

<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Quiz App - Startseite</title>
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css">
    <style>
        body {
            background-color: #f8f9fa;
        }
        .welcome-icon {
            font-size: 4rem;
            color: #007bff;
            margin-bottom: 1rem;
        }
        .btn-custom-welcome {
            font-size: 1.2rem;
            padding: 0.75rem 1.5rem;
            margin-bottom: 1rem;
            width: 100%;
        }
        .admin-buttons .btn-custom {
            margin-bottom: 0.75rem;
        
        }
 
        .admin-buttons {
            margin-bottom: 2rem;
        }
        
        .card {
            border: none;
            border-radius: 15px;
            box-shadow: 0 0.5rem 1rem rgba(0, 0, 0, 0.15);
        }
    </style>
</head>
<body>
    <div class="container mt-5 mb-5">
        <div class="text-center">
            <i class="fas fa-user-graduate welcome-icon"></i>
            <h1 class="mb-4">Willkommen zur Quiz App</h1>
            <?php if (isset($_SESSION['message'])): ?>
                <div class='alert alert-success mt-3 mb-3'>
                    <?php 
                    echo $_SESSION['message'];
                    unset($_SESSION['message']);
                    ?>
                </div>
            <?php endif; ?>
        </div>

        <div class="row justify-content-center">
            <div class="col-md-6">
                <a href="quiz.php" class="btn btn-primary btn-custom-welcome d-block"><i class="fas fa-play"></i> Quiz starten</a>
                               
                <?php if ($hasStats): ?>
                    <a href="statistics.php" class="btn btn-info btn-custom-welcome d-block"><i class="fas fa-chart-pie"></i> Fragestatistik anzeigen</a>
                    <form action="reset.php" method="post" class="mb-3">
                    <button type="submit" class="btn btn-warning btn-custom-welcome d-block"><i class="fas fa-chart-bar"></i> Fragenstatistik zurücksetzen</button>
                </form>
                <?php endif; ?>

                <?php if ($userRole === ROLE_ADMIN || $userRole === ROLE_EDITOR || $userRole === ROLE_MANAGER): ?>
                    <div class="card admin-buttons mt-4">
                        <div class="card-body">
                            <h2 class="card-title text-center mb-3">Verwaltung</h2>
                            <?php if ($userRole === ROLE_ADMIN || $userRole === ROLE_EDITOR): ?>
                                <a href="admin/manage_questions.php" class="btn btn-secondary btn-custom d-block"><i class="fas fa-tasks"></i> Fragen verwalten</a>
                                <a href="admin/manage_categories.php" class="btn btn-secondary btn-custom d-block"><i class="fas fa-list"></i> Kategorien verwalten</a>
                            <?php endif; ?>
                            <?php if ($userRole === ROLE_ADMIN || $userRole === ROLE_MANAGER): ?>
                                <a href="admin/export_questions.php" class="btn btn-secondary btn-custom d-block"><i class="fas fa-file-export"></i> Fragen exportieren</a>
                                <a href="admin/import_questions.php" class="btn btn-secondary btn-custom d-block"><i class="fas fa-file-import"></i> Fragen importieren</a>
                            <?php endif; ?>
                            <?php if ($userRole === ROLE_ADMIN): ?>
                                <a href="admin/user_management.php" class="btn btn-secondary btn-custom d-block"><i class="fas fa-users-cog"></i> Benutzerverwaltung</a>
                                <a href="admin/settings.php" class="btn btn-secondary btn-custom d-block"><i class="fas fa-cogs"></i> Einstellungen</a>
                            <?php endif; ?>
                        </div>
                    </div>
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
