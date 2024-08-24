<?php
session_start();
require_once '../config.php';
checkUserRole(ROLE_ADMIN);
require '../vendor/autoload.php'; // Wenn Sie Composer verwenden

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

$message = ''; // Variable für die Nachricht

function sendTestEmail($to) {
    $mail = new PHPMailer(true);

    try {
        echo "PHPMailer wird konfiguriert...<br>"; // Debugging-Information

        // Server settings
        $mail->isSMTP();
        $mail->Host       = SMTP_HOST;
        echo "SMTP_HOST: " . SMTP_HOST . "<br>"; // Debugging-Information
        $mail->SMTPAuth   = true;
        $mail->Username   = SMTP_USER;
        echo "SMTP_USER: " . SMTP_USER . "<br>"; // Debugging-Information
        $mail->Password   = SMTP_PASS;
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS; // Für SSL verwenden Sie SMTPS
        $mail->Port       = SMTP_PORT;
        echo "SMTP_PORT: " . SMTP_PORT . "<br>"; // Debugging-Information

        // Empfänger
        $mail->setFrom(SMTP_FROM, SMTP_FROM_NAME);
        echo "Von: " . SMTP_FROM . " (" . SMTP_FROM_NAME . ")<br>"; // Debugging-Information
        $mail->addAddress($to);
        echo "An: " . $to . "<br>"; // Debugging-Information

        // Inhalt der E-Mail
        $mail->isHTML(true);
        $mail->Subject = 'Test E-Mail von Quiz App';
        $mail->Body    = 'Dies ist eine Test-E-Mail von der Quiz App. Wenn Sie diese E-Mail erhalten, funktioniert der E-Mail-Versand mit PHPMailer korrekt.';

        // E-Mail senden
        echo "E-Mail wird gesendet...<br>"; // Debugging-Information
        $mail->send();
        echo "E-Mail wurde erfolgreich gesendet!<br>"; // Debugging-Information
        return true;
    } catch (Exception $e) {
        echo "Fehler beim Senden der E-Mail: {$mail->ErrorInfo}<br>"; // Debugging-Information
        return "Mailer Error: {$mail->ErrorInfo}";
    }
}

// Test-E-Mail senden, wenn das Formular abgeschickt wird
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    echo "Formular wurde abgeschickt.<br>"; // Debugging-Information
    $testEmailAddress = $_POST['email']; // E-Mail-Adresse aus dem Formular
    echo "Test-E-Mail-Adresse: " . $testEmailAddress . "<br>"; // Debugging-Information
    $sendResult = sendTestEmail($testEmailAddress);

    if ($sendResult === true) {
        $message = "Test-E-Mail wurde erfolgreich gesendet. Bitte überprüfen Sie Ihren Posteingang (und ggf. den Spam-Ordner) für die E-Mail an: " . $testEmailAddress;
    } else {
        $message = "Fehler beim Senden der Test-E-Mail: " . $sendResult;
    }
}
?>

<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Test E-Mail - Quiz App</title>
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
</head>
<body>
    <div class="container mt-5">
        <h1 class="text-center mb-4">Test E-Mail senden</h1>

        <?php if ($message): ?>
            <div class="alert alert-info"><?php echo $message; ?></div>
        <?php endif; ?>

        <form method="post" action="">
            <div class="form-group">
                <label for="email">E-Mail-Adresse:</label>
                <input type="email" class="form-control" id="email" name="email" required>
            </div>
            <button type="submit" class="btn btn-primary">Test-E-Mail senden</button>
        </form>

        <div class="mt-3">
            <a href="index.php">Zurück zur Startseite</a>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.5.1.slim.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.5.3/dist/umd/popper.min.js"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>
</body>
</html>