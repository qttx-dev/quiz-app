<?php
session_start();
require_once 'config.php';

$error = '';
$success = '';

// Überprüfen, ob die Selbstregistrierung aktiviert ist
$stmt = $db->query("SELECT value FROM settings WHERE name = 'allow_self_registration'");
$allowSelfRegistration = $stmt->fetchColumn();

if ($allowSelfRegistration != '1') {
    die("Die Selbstregistrierung ist derzeit deaktiviert.");
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $username = $_POST['username'];
    $email = $_POST['email'];
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];

    // Überprüfung der Passwortstärke
    if (strlen($password) < 12 || !preg_match('/[A-Z]/', $password) || !preg_match('/[a-z]/', $password) || !preg_match('/[0-9]/', $password) || !preg_match('/[^A-Za-z0-9]/', $password)) {
        $error = 'Das Passwort erfüllt nicht die Sicherheitsanforderungen.';
    } elseif ($password !== $confirm_password) {
        $error = 'Die Passwörter stimmen nicht überein.';
    } else {
        $stmt = $db->prepare("SELECT * FROM users WHERE username = ? OR email = ?");
        $stmt->execute([$username, $email]);
        if ($stmt->rowCount() > 0) {
            $error = 'Benutzername oder E-Mail-Adresse bereits vergeben.';
        } else {
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $db->prepare("INSERT INTO users (username, email, password, role) VALUES (?, ?, ?, ?)");
            if ($stmt->execute([$username, $email, $hashed_password, ROLE_USER])) {
                $success = 'Registrierung erfolgreich. Sie können sich jetzt anmelden.';
            } else {
                $error = 'Ein Fehler ist aufgetreten. Bitte versuchen Sie es später erneut.';
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Registrierung - Quiz App</title>
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <style>
        #password-strength-meter {
            width: 100%;
            height: 10px;
            margin-top: 5px;
        }
    </style>
</head>
<body>
    <div class="container mt-5">
        <h1 class="mb-4">Registrierung</h1>
        <?php if ($error): ?>
            <div class="alert alert-danger"><?php echo $error; ?></div>
        <?php endif; ?>
        <?php if ($success): ?>
            <div class="alert alert-success"><?php echo $success; ?></div>
        <?php else: ?>
            <form method="post" id="registration-form">
                <div class="form-group">
                    <label for="username">Benutzername:</label>
                    <input type="text" class="form-control" id="username" name="username" required>
                </div>
                <div class="form-group">
                    <label for="email">E-Mail-Adresse:</label>
                    <input type="email" class="form-control" id="email" name="email" required>
                </div>
                <div class="form-group">
                    <label for="password">Passwort:</label>
                    <input type="password" class="form-control" id="password" name="password" required>
                    <div id="password-strength-meter"></div>
                    <small id="passwordHelpBlock" class="form-text text-muted">
                        Das Passwort muss mindestens 12 Zeichen lang sein und Großbuchstaben, Kleinbuchstaben, Zahlen und Sonderzeichen enthalten.
                    </small>
                </div>
                <div class="form-group">
                    <label for="confirm_password">Passwort bestätigen:</label>
                    <input type="password" class="form-control" id="confirm_password" name="confirm_password" required>
                </div>
                <button type="submit" class="btn btn-primary" id="submit-btn" disabled>Registrieren</button>
            </form>
        <?php endif; ?>
        <p class="mt-3">
            Bereits ein Konto? <a href="login.php">Anmelden</a>
        </p>
    </div>

    <script>
    document.addEventListener('DOMContentLoaded', function() {
        const passwordInput = document.getElementById('password');
        const confirmPasswordInput = document.getElementById('confirm_password');
        const submitButton = document.getElementById('submit-btn');
        const strengthMeter = document.getElementById('password-strength-meter');

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
                strengthMeter.style.backgroundColor = 'red';
            } else if (strength < 5) {
                strengthMeter.style.backgroundColor = 'orange';
            } else {
                strengthMeter.style.backgroundColor = 'green';
            }

            submitButton.disabled = strength < 5 || password !== confirmPasswordInput.value;
        }

        passwordInput.addEventListener('input', updatePasswordStrength);
        confirmPasswordInput.addEventListener('input', updatePasswordStrength);
    });
    </script>
</body>
</html>