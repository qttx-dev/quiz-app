<?php
session_start();
require_once 'config.php';

// Überprüfen, ob der Benutzer angemeldet ist
checkUserRole(ROLE_USER);

// Funktion zum Abrufen der Benutzerstatistik
function getUserStatistics($db, $userId) {
    try {
        $stmt = $db->prepare("
            SELECT 
                SUM(correct_count) as total_correct,
                SUM(incorrect_count) as total_incorrect
            FROM user_statistics
            WHERE user_id = ?
        ");
        $stmt->execute([$userId]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Fehler beim Abrufen der Benutzerstatistik: " . $e->getMessage());
        return false;
    }
}

// Angenommen, wir haben die Benutzer-ID in der Session
$userId = $_SESSION['user_id'];
$userStats = getUserStatistics($db, $userId);

if ($userStats) {
    $totalCorrect = $userStats['total_correct'];
    $totalIncorrect = $userStats['total_incorrect'];
    $totalQuestions = $totalCorrect + $totalIncorrect;
    $correctPercentage = $totalQuestions > 0 ? ($totalCorrect / $totalQuestions) * 100 : 0;
    $incorrectPercentage = $totalQuestions > 0 ? ($totalIncorrect / $totalQuestions) * 100 : 0;
}
?>

<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Quiz-Statistik</title>
    <link href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.1/css/all.min.css" rel="stylesheet">
    <style>
        .bg-gradient-success {
            background: linear-gradient(45deg, #28a745, #34ce57);
        }
        .bg-gradient-danger {
            background: linear-gradient(45deg, #dc3545, #f25d69);
        }
        .card {
            border: none;
            border-radius: 15px;
            transition: transform 0.3s;
        }
        .card:hover {
            transform: translateY(-5px);
        }
        .shadow {
            box-shadow: 0 0.5rem 1rem rgba(0, 0, 0, 0.15);
        }
        .shadow-sm {
            box-shadow: 0 0.125rem 0.25rem rgba(0, 0, 0, 0.075);
        }
        .display-4 {
            font-weight: 600;
        }
        @media (max-width: 768px) {
            .display-4 {
                font-size: 2.5rem;
            }
        }
    </style>
</head>
<body>
    <div class="container mt-5">
        <h2 class="text-center mb-4"><i class="fas fa-chart-pie"></i> Ihre Quiz-Statistik</h2>
        <?php if ($userStats): ?>
            <div class="row justify-content-center">
                <div class="col-md-4 mb-3">
                    <div class="card bg-gradient-success text-white shadow">
                        <div class="card-body text-center">
                            <h5 class="card-title"><i class="fas fa-check-circle fa-3x mb-3"></i></h5>
                            <p class="card-text h5">Richtig beantwortet</p>
                            <h2 class="card-text display-4"><?php echo $totalCorrect; ?></h2>
                            <p class="card-text h4"><?php echo number_format($correctPercentage, 1); ?>%</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-4 mb-3">
                    <div class="card bg-gradient-danger text-white shadow">
                        <div class="card-body text-center">
                            <h5 class="card-title"><i class="fas fa-times-circle fa-3x mb-3"></i></h5>
                            <p class="card-text h5">Falsch beantwortet</p>
                            <h2 class="card-text display-4"><?php echo $totalIncorrect; ?></h2>
                            <p class="card-text h4"><?php echo number_format($incorrectPercentage, 1); ?>%</p>
                        </div>
                    </div>
                </div>
            </div>
            <div class="row justify-content-center mt-4">
                <div class="col-md-8">
                    <div class="card bg-light shadow-sm">
                        <div class="card-body text-center">
                            <h5 class="card-title"><i class="fas fa-question-circle fa-2x mb-3 text-primary"></i></h5>
                            <p class="card-text h5">Gesamtzahl der beantworteten Fragen</p>
                            <h2 class="card-text display-4 text-primary"><?php echo $totalQuestions; ?></h2>
                        </div>
                    </div>
                </div>
            </div>
        <?php else: ?>
            <div class="alert alert-warning" role="alert">
                Keine Statistiken verfügbar. Versuchen Sie, ein Quiz zu spielen!
            </div>
        <?php endif; ?>
        <div class="row justify-content-center mt-5 mb-5">
        <div class="col-md-6 text-center">
            <a href="index.php" class="btn btn-primary btn-lg btn-block">
                <i class="fas fa-home mr-2"></i> Zurück zur Startseite
            </a>
        </div>
    </div>
    </div>
</body>
</html>
