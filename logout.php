<?php
// Start the session
session_start();

// Verbindung zur Datenbank herstellen
require 'config.php';

try {
    $db = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME, DB_USER, DB_PASS);
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    if (isset($_SESSION['user_id'])) {
        // Aktualisieren des logged_in und logged_out Status
        $update_stmt = $db->prepare("UPDATE users SET logged_in = FALSE, logged_out = NOW() WHERE id = :user_id");
        $update_stmt->bindParam(':user_id', $_SESSION['user_id']);
        $update_stmt->execute();
    }
} catch (PDOException $e) {
    error_log("Fehler beim Aktualisieren des Logout-Status: " . $e->getMessage());
}

// Unset all session variables
$_SESSION = array();

// Destroy the session cookie
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

// Destroy the session
session_destroy();

// Redirect to the login page
header("Location: login.php");
exit();
?>
