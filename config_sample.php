<?php
// Datenbankeinstellungen
define('DB_HOST', 'localhost');
define('DB_NAME', 'database');
define('DB_USER', 'user');
define('DB_PASS', 'password');

try {
    $db = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME, DB_USER, DB_PASS);
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("Verbindung zur Datenbank fehlgeschlagen: " . $e->getMessage());
}

// Mailserver Einstellungen. Möglich mit Port 587 STARTLS und 465 SSL/TLS über PHPMailer
define('SMTP_HOST', 'mail.server.tld');
define('SMTP_PORT', 465);
define('SMTP_USER', 'quiz@domain.tld');
define('SMTP_PASS', 'password');
define('SMTP_FROM', 'quiz@domain.tld');
define('SMTP_FROM_NAME', 'Quiz App');
define('SMTP_SECURE', 'tls');

// Rollensystem
define('ROLE_USER', 'user');
define('ROLE_EDITOR', 'editor');
define('ROLE_MANAGER', 'manager');
define('ROLE_ADMIN', 'admin');

// Funktion zur Prüfung der User-Rolle
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