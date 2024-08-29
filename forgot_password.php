<?php
session_start();
require_once 'config.php';
require 'vendor/autoload.php'; // Include Composer autoload if using Composer

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

$message = '';

function sendPasswordResetEmail($email, $token, $baseUrl) {
    $mail = new PHPMailer(true);

    try {
        // Server settings
        $mail->isSMTP();
        $mail->Host       = SMTP_HOST;
        $mail->SMTPAuth   = true;
        $mail->Username   = SMTP_USER;
        $mail->Password   = SMTP_PASS;
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS; // For SSL
        $mail->Port       = SMTP_PORT;

        // Recipient
        $mail->setFrom(SMTP_FROM, SMTP_FROM_NAME);
        $mail->addAddress($email);

        // Email content
        $mail->isHTML(true);
        $mail->CharSet = 'UTF-8';
        $mail->Subject = "Passwort zurücksetzen - Quiz App";
        $mail->Body    = "
        <!DOCTYPE html>
        <html lang='de'>
        <head>
            <meta charset='UTF-8'>
            <meta name='viewport' content='width=device-width, initial-scale=1.0'>
            <title>Passwort zurücksetzen - Quiz App</title>
            <link rel='stylesheet' href='https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css'>
            <link rel='stylesheet' href='https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css'>
            <style>
                body {
                    background-color: #f8f9fa;
                }
                .container {
                    max-width: 600px;
                    margin: 0 auto;
                    padding: 20px;
                    background: #ffffff;
                    border-radius: 15px;
                    box-shadow: 0 0.5rem 1rem rgba(0, 0, 0, 0.15);
                }
                .header {
                    background-color: #007bff;
                    color: white;
                    text-align: center;
                    padding: 10px;
                    border-radius: 15px 15px 0 0;
                }
                .button {
                    display: inline-block;
                    background-color: #007bff;
                    color: white;
                    padding: 10px 20px;
                    text-decoration: none;
                    border-radius: 5px;
                }
                .link {
                    margin-top: 10px;
                    display: block;
                    color: #007bff;
                    text-decoration: underline;
                }
            </style>
        </head>
        <body>
            <div class='container'>
                <div class='header'>
                    <h1>Quiz App - Passwort zurücksetzen</h1>
                </div>
                <div class='content'>
                    <p>Hallo,</p>
                    <p>Sie haben eine Anfrage zum Zurücksetzen Ihres Passworts gestellt. Bitte klicken Sie auf den folgenden Button, um Ihr Passwort zurückzusetzen:</p>
                    <p style='text-align: center;'>
                        <a href='{$baseUrl}/password.php?token=$token' class='button'>Passwort zurücksetzen</a>
                    </p>
                    <p>Falls der Button nicht funktioniert, können Sie den folgenden Link kopieren und in Ihren Browser einfügen:</p>
                    <p style='text-align: center;'>
                        <a href='{$baseUrl}/password.php?token=$token' class='link'>{$baseUrl}/password.php?token=$token</a>
                    </p>
                    <p>Bitte beachten Sie, dass dieser Link nur für eine Stunde gültig ist.</p>
                    <p>Wenn Sie diese Anfrage nicht gestellt haben, können Sie diese E-Mail ignorieren.</p>
                    <p>Mit freundlichen Grüßen,<br>Ihr Quiz App Team</p>
                </div>
            </div>
        </body>
        </html>
        ";

        // Send email
        $mail->send();
        return true;
    } catch (Exception $e) {
        return "Mailer Error: {$mail->ErrorInfo}";
    }
}

// Automatic domain detection
$protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http";
$domain = $_SERVER['HTTP_HOST'];
$baseUrl = $protocol . "://" . $domain;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = $_POST['email'];

    try {
        $stmt = $db->prepare("SELECT * FROM users WHERE email = ?");
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        if ($user) {
            $token = bin2hex(random_bytes(32));
            $expiry = date('Y-m-d H:i:s', strtotime('+1 hour'));

            $updateStmt = $db->prepare("UPDATE users SET reset_token = ?, reset_token_expiry = ? WHERE id = ?");
            $updateStmt->execute([$token, $expiry, $user['id']]);

            // Send email
            if (sendPasswordResetEmail($email, $token, $baseUrl) === true) {
                $message = "Ein Link zum Zurücksetzen des Passworts wurde an Ihre E-Mail-Adresse gesendet.";
            } else {
                $message = "Fehler beim Senden der E-Mail.";
            }
        } else {
            $message = "Es wurde kein Konto mit dieser E-Mail-Adresse gefunden.";
        }
    } catch (PDOException $e) {
        $message = "Fehler: " . $e->getMessage();
    }
}
?>

<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Passwort vergessen - Quiz App</title>
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css">
    <style>
        body {
            background-color: #f8f9fa;
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
        .welcome-icon {
            font-size: 4rem;
            color: #007bff;
            margin-bottom: 1rem;
        }
    </style>
</head>
<body>
    <div class="container mt-5">
        <div class="text-center">
            <i class="fas fa-user-graduate welcome-icon"></i>
            <h1 class="mb-4">Passwort vergessen</h1>
        </div>
        <div class="login-container">
            <?php if ($message): ?>
                <div class="alert alert-info"><?php echo $message; ?></div>
            <?php endif; ?>

            <form method="post" action="<?php echo $baseUrl; ?>/forgot_password.php">
                <div class="form-group text-left">
                    <label for="email">E-Mail-Adresse</label>
                    <input type="email" class="form-control" id="email" name="email" required>
                </div>
                <button type="submit" class="btn btn-primary btn-custom"><i class="fas fa-paper-plane"></i> Passwort zurücksetzen</button>
            </form>
            <div class="text-center">
                <a class="link-pwd" href="<?php echo $baseUrl; ?>/login.php">Zurück zur Anmeldung</a>
            </div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.5.1.slim.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.5.3/dist/umd/popper.min.js"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>
</body>
</html>