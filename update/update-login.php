<?php
// Einbinden der Konfigurationsdatei
require_once '../config.php';

// Aktivieren von Fehlerberichten
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

try {
    // Verbindung zur Datenbank herstellen
    $conn = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME, DB_USER, DB_PASS);
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Überprüfen, ob die Spalte 'logged_in' existiert
    $stmt = $conn->prepare("SHOW COLUMNS FROM users LIKE 'logged_in'");
    $stmt->execute();
    $loggedInExists = $stmt->rowCount() > 0;

    // Überprüfen, ob die Spalte 'logged_out' existiert
    $stmt = $conn->prepare("SHOW COLUMNS FROM users LIKE 'logged_out'");
    $stmt->execute();
    $loggedOutExists = $stmt->rowCount() > 0;

    // Überprüfen, ob die Spalte 'last_login' existiert
    $stmt = $conn->prepare("SHOW COLUMNS FROM users LIKE 'last_login'");
    $stmt->execute();
    $lastLoginExists = $stmt->rowCount() > 0;

    // Spalte 'logged_in' hinzufügen, falls sie nicht existiert
    if (!$loggedInExists) {
        $conn->exec("ALTER TABLE users ADD COLUMN logged_in BOOLEAN DEFAULT FALSE");
        echo "Spalte 'logged_in' erfolgreich hinzugefügt.<br>";
    } else {
        echo "Spalte 'logged_in' existiert bereits.<br>";
    }

    // Spalte 'logged_out' hinzufügen, falls sie nicht existiert
    if (!$loggedOutExists) {
        $conn->exec("ALTER TABLE users ADD COLUMN logged_out DATETIME DEFAULT NULL");
        echo "Spalte 'logged_out' erfolgreich hinzugefügt.<br>";
    } else {
        echo "Spalte 'logged_out' existiert bereits.<br>";
    }

    // Spalte 'last_login' hinzufügen, falls sie nicht existiert
    if (!$lastLoginExists) {
        $conn->exec("ALTER TABLE users ADD COLUMN last_login DATETIME DEFAULT NULL");
        echo "Spalte 'last_login' erfolgreich hinzugefügt.<br>";
    } else {
        echo "Spalte 'last_login' existiert bereits.<br>";
    }

} catch (PDOException $e) {
    echo "Fehler: " . $e->getMessage();
}
?>
