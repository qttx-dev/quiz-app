<?php
session_start();
require_once '../config.php'; // Pfad zu config.php anpassen

// Überprüfen, ob der Benutzer angemeldet ist und die Rolle Admin hat
checkUserRole(ROLE_ADMIN);

function getAllUsers($db) {
    try {
        $stmt = $db->query("SELECT id, username, email, role, last_login, logged_out, logged_in FROM users ORDER BY username");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Fehler beim Abrufen der Benutzer: " . $e->getMessage());
        return [];
    }
}

$users = getAllUsers($db);
$message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete'])) {
    $userId = $_POST['user_id'];
    $stmt = $db->prepare("DELETE FROM users WHERE id = ?");
    if ($stmt->execute([$userId])) {
        $message = "Benutzer erfolgreich gelöscht.";
        $users = getAllUsers($db); // Aktualisiere die Benutzerliste
    } else {
        $message = "Fehler beim Löschen des Benutzers.";
    }
}

function formatDateTime($dateTime) {
    if ($dateTime) {
        return date("d.m.Y H:i:s", strtotime($dateTime));
    }
    return "N/A";
}

function calculateSessionDuration($lastLogin, $loggedOut) {
    if ($lastLogin && $loggedOut) {
        $start = new DateTime($lastLogin);
        $end = new DateTime($loggedOut);
        $interval = $start->diff($end);
        if ($interval->h > 0) {
            return $interval->h . " Stunden, " . $interval->i . " Minuten";
        } else {
            return $interval->i . " Minuten";
        }
    }
    return "N/A";
}
?>

<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Benutzerverwaltung - Quiz App</title>
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css">
    <style>
        .status-indicator {
            width: 10px;
            height: 10px;
            border-radius: 50%;
            display: inline-block;
        }
        .status-online {
            background-color: green;
        }
        .status-offline {
            background-color: red;
        }
    </style>
</head>
<body>
    <div class="container mt-5">
        <h1 class="mb-4">Benutzerverwaltung</h1>
        
        <?php if ($message): ?>
            <div class="alert alert-info"><?php echo $message; ?></div>
        <?php endif; ?>

        <div class="mb-3">
            <a href="admin_create_user.php" class="btn btn-primary">
                <i class="fas fa-user-plus"></i> Neuen Benutzer anlegen
            </a>
        </div>

        <table class="table table-striped">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Benutzername</th>
                    <th>E-Mail</th>
                    <th>Rolle</th>
                    <th>Status</th>
                    <th>Letzter Login</th>
                    <th>Logout</th>
                    <th>Session Dauer</th>
                    <th>Aktion</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($users as $user): ?>
                    <tr>
                        <td><?php echo $user['id']; ?></td>
                        <td><?php echo htmlspecialchars($user['username']); ?></td>
                        <td><?php echo htmlspecialchars($user['email']); ?></td>
                        <td><?php echo htmlspecialchars($user['role']); ?></td>
                        <td>
                            <span class="status-indicator <?php echo $user['logged_in'] ? 'status-online' : 'status-offline'; ?>"></span>
                        </td>
                        <td><?php echo formatDateTime($user['last_login']); ?></td>
                        <td><?php echo formatDateTime($user['logged_out']); ?></td>
                        <td><?php echo calculateSessionDuration($user['last_login'], $user['logged_out']); ?></td>
                        <td>
                            <form action="" method="post" onsubmit="return confirm('Sind Sie sicher, dass Sie diesen Benutzer löschen möchten?');">
                                <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                                <button type="submit" name="delete" class="btn btn-danger btn-sm">
                                    <i class="fas fa-trash"></i> Löschen
                                </button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <a href="../index.php" class="btn btn-secondary mt-3">
            <i class="fas fa-arrow-left"></i> Zurück zur Startseite
        </a>
    </div>

    <?php include '../footer.php'; ?>

    <script src="https://code.jquery.com/jquery-3.5.1.slim.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.5.3/dist/umd/popper.min.js"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>
</body>
</html>
