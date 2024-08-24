<?php
session_start();
require_once 'config.php';

$message = '';
$token = isset($_GET['token']) ? $_GET['token'] : '';

// Funktion zur Überprüfung der Passwortstärke
function isPasswordStrong($password) {
    return (strlen($password) >= 12 &&
            preg_match('/[A-Z]/', $password) &&
            preg_match('/[a-z]/', $password) &&
            preg_match('/[0-9]/', $password) &&
            preg_match('/[^A-Za-z0-9]/', $password));
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $newPassword = $_POST['new_password'];
    $confirmPassword = $_POST['confirm_password'];
    $token = $_POST['token'];

    if ($newPassword !== $confirmPassword) {
        $message = "Die Passwörter stimmen nicht überein.";
    } elseif (!isPasswordStrong($newPassword)) {
        $message = "Das Passwort muss mindestens 12 Zeichen lang sein und Großbuchstaben, Kleinbuchstaben, Zahlen und Sonderzeichen enthalten.";
    } else {
        try {
            $stmt = $db->prepare("SELECT * FROM users WHERE reset_token = ? AND reset_token_expiry > NOW()");
            $stmt->execute([$token]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($user) {
                $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
                $updateStmt = $db->prepare("UPDATE users SET password = ?, reset_token = NULL, reset_token_expiry = NULL WHERE id = ?");
                $updateStmt->execute([$hashedPassword, $user['id']]);
                $message = "Ihr Passwort wurde erfolgreich zurückgesetzt. Sie können sich jetzt anmelden.";
            } else {
                $message = "Ungültiger oder abgelaufener Token.";
            }
        } catch (PDOException $e) {
            $message = "Fehler beim Zurücksetzen des Passworts: " . $e->getMessage();
        }
    }
}

// Automatische Ermittlung der Domain
$protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http";
$domain = $_SERVER['HTTP_HOST'];
$baseUrl = $protocol . "://" . $domain;
?>


<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Passwort zurücksetzen - Quiz App</title>
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
</head>
<body>
    <div class="container mt-5">
        <h1 class="text-center mb-4">Passwort zurücksetzen</h1>
        
        <?php if ($message): ?>
            <div class="alert alert-info"><?php echo $message; ?></div>
        <?php endif; ?>

        <?php if (!$message || strpos($message, 'Fehler') !== false): ?>
            <form method="post" action="<?php echo $baseUrl; ?>/password.php">
                <input type="hidden" name="token" value="<?php echo htmlspecialchars($token); ?>">
                <div class="form-group">
                    <label for="new_password">Neues Passwort:</label>
                    <input type="password" class="form-control" id="new_password" name="new_password" required>
                    <div id="password-strength-meter" class="progress mt-2" style="height: 5px;">
                        <div class="progress-bar" role="progressbar" style="width: 0%;" aria-valuenow="0" aria-valuemin="0" aria-valuemax="100"></div>
                    </div>
                    <small id="passwordHelpBlock" class="form-text text-muted">
                        Das Passwort muss mindestens 12 Zeichen lang sein und Großbuchstaben, Kleinbuchstaben, Zahlen und Sonderzeichen enthalten.
                    </small>
                </div>
                <div class="form-group">
                    <label for="confirm_password">Passwort bestätigen:</label>
                    <input type="password" class="form-control" id="confirm_password" name="confirm_password" required>
                </div>
                <button type="submit" class="btn btn-primary">Passwort zurücksetzen</button>
            </form>
        <?php endif; ?>

        <div class="mt-3">
            <a href="<?php echo $baseUrl; ?>/login.php">Zurück zur Anmeldung</a>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.5.1.slim.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.5.3/dist/umd/popper.min.js"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>

    <script>
    document.addEventListener('DOMContentLoaded', function() {
        const passwordInput = document.getElementById('new_password');
        const confirmPasswordInput = document.getElementById('confirm_password');
        const submitButton = document.querySelector('button[type="submit"]');
        const strengthMeter = document.querySelector('#password-strength-meter .progress-bar');

        function checkPasswordStrength(password) {
            let strength = 0;
            if (password.length >= 12) strength += 1;
            if (/[A-Z]/.test(password)) strength += 1;
            if (/[a-z]/.test(password)) strength += 1;
            if (/[0-9]/.test(password)) strength += 1;
            if (/[^A-Za-z0-9]/.test(password)) strength += 1;
            return strength;
        }

        function updatePasswordStrength() {
            const password = passwordInput.value;
            const strength = checkPasswordStrength(password);
            
            strengthMeter.style.width = `${strength * 20}%`;
            
            if (strength < 3) {
                strengthMeter.className = 'progress-bar bg-danger';
            } else if (strength < 5) {
                strengthMeter.className = 'progress-bar bg-warning';
            } else {
                strengthMeter.className = 'progress-bar bg-success';
            }

            submitButton.disabled = strength < 5 || password !== confirmPasswordInput.value;
        }

        passwordInput.addEventListener('input', updatePasswordStrength);
        confirmPasswordInput.addEventListener('input', updatePasswordStrength);
    });
    </script>
</body>
</html>