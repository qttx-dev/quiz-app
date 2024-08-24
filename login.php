<?php
session_start();
session_regenerate_id(true);
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

$message = '';

// Überprüfen, ob der Benutzer bereits eingeloggt ist
if (isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit();
}

// Überprüfen der Login-Daten
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = $_POST['username'];
    $password = $_POST['password'];

    // Verbindung zur Datenbank herstellen
    require 'config.php';
    try {
        $db = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME, DB_USER, DB_PASS);
        $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        // Benutzer in der Datenbank suchen
        $stmt = $db->prepare("SELECT * FROM users WHERE username = :username");
        $stmt->bindParam(':username', $username);
        $stmt->execute();
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($user && password_verify($password, $user['password'])) {
            // Login erfolgreich
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['user_role'] = $user['role'];

            // Aktualisieren des last_login Zeitpunkts und logged_in Status
            $update_stmt = $db->prepare("UPDATE users SET last_login = NOW(), logged_in = TRUE WHERE id = :user_id");
            $update_stmt->bindParam(':user_id', $user['id']);
            $update_stmt->execute();

            header('Location: index.php');
            exit();
        } else {
            $message = 'Ungültiger Benutzername oder Passwort.';
        }
    } catch (PDOException $e) {
        $message = 'Datenbankfehler: ' . $e->getMessage();
    }
}
?>

<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Quiz App - Login</title>
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
        .login-container {
            max-width: 400px;
            margin: auto;
            padding: 2rem;
            background: #ffffff;
            border-radius: 15px;
            box-shadow: 0 0.5rem 1rem rgba(0, 0, 0, 0.15);
        }
        .btn-custom {
            font-size: 1.2rem;
            padding: 0.75rem 1.5rem;
            width: 100%;
        }

        .link-pwd {
            font-size: 0.8rem;
        }
    </style>
</head>
<body>
    <div class="container mt-5">
        <div class="text-center">
            <i class="fas fa-user-graduate welcome-icon"></i>
            <h1 class="mb-4">Quiz App</h1>
        </div>
        <div class="login-container">
            <?php if ($message): ?>
                <div class="alert alert-danger"><?php echo $message; ?></div>
            <?php endif; ?>

            <form method="post" action="">
                <div class="form-group text-left">
                    <label for="username">Benutzername</label>
                    <input type="text" class="form-control" id="username" name="username" required>
                </div>
                <div class="form-group text-left">
                    <label for="password">Kennwort</label>
                    <input type="password" class="form-control" id="password" name="password" required>
                </div>
                <button type="submit" class="btn btn-primary btn-custom"><i class="fas fa-sign-in-alt"></i> Login</button>
            </form>
            <div class="text-center">
                <a class="link-pwd" href="forgot_password.php">Passwort vergessen</a>
            </div>
        </div>
        <div class="text-center mt-4">
            <?php include 'inc/footer_text_inc.php'; ?>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.5.1.slim.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.5.3/dist/umd/popper.min.js"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>
</body>
</html>