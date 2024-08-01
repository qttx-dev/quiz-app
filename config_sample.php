<?php

// Datenbank-Konfiguration
define('DB_HOST', 'localhost');
define('DB_NAME', 'quiz_app');
define('DB_USER', 'your_username');
define('DB_PASS', 'your_password');

// PHPMailer-Konfiguration
define('SMTP_HOST', 'smtp.example.com');
define('SMTP_PORT', 587); // Ändern Sie dies zu 465, wenn Sie SSL verwenden möchten
define('SMTP_USER', 'your_email@example.com');
define('SMTP_PASS', 'your_email_password');
define('SMTP_FROM', 'noreply@example.com');
define('SMTP_FROM_NAME', 'Quiz App');
define('SMTP_SECURE', SMTP_PORT == 465 ? 'ssl' : 'tls'); // Automatische Auswahl des Verschlüsselungstyps

// Benutzerrollen
define('ROLE_USER', 'user');
define('ROLE_MANAGER', 'manager');
define('ROLE_EDITOR', 'editor');
define('ROLE_ADMIN', 'admin');

// Datenbankverbindung herstellen
try {
    $db = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME, DB_USER, DB_PASS);
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("Verbindung zur Datenbank fehlgeschlagen: " . $e->getMessage());
}
// try {
//     $db = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8", DB_USER, DB_PASS);
//     $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
// } catch(PDOException $e) {
//     die("Verbindung zur Datenbank fehlgeschlagen: " . $e->getMessage());
// }

// Funktion zur Überprüfung der Benutzerrechte
function checkUserRole($requiredRole) {
    if (!isset($_SESSION['user_role'])) {
        header('Location: login.php');
        exit();
    }

    $userRole = $_SESSION['user_role'];
    $roles = [ROLE_USER, ROLE_EDITOR, ROLE_MANAGER, ROLE_ADMIN];
    $userRoleIndex = array_search($userRole, $roles);
    $requiredRoleIndex = array_search($requiredRole, $roles);

    if ($userRoleIndex === false || ($userRoleIndex < $requiredRoleIndex && $userRole !== ROLE_ADMIN)) {
        header('Location: index.php');
        exit();
    }
}

?>
