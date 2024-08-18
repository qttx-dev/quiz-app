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

    // Tabelle für globale Einstellungen anlegen (falls noch nicht vorhanden)
    $settings_table = 'settings';
    $stmt = $conn->prepare("SHOW TABLES LIKE '$settings_table'");
    $stmt->execute();
    if ($stmt->rowCount() === 0) {
        $conn->exec("CREATE TABLE $settings_table (
            id INT AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(255) UNIQUE,
            value VARCHAR(255)
        )");
        echo "Tabelle '$settings_table' erfolgreich erstellt.<br>";
    }

    // Überprüfen und ggf. hinzufügen der Spalten (falls Tabelle schon existiert)
    $columns = ['name', 'value'];
    foreach ($columns as $column) {
        $stmt = $conn->prepare("SHOW COLUMNS FROM $settings_table LIKE '$column'");
        $stmt->execute();
        if ($stmt->rowCount() === 0) {
            $conn->exec("ALTER TABLE $settings_table ADD COLUMN $column VARCHAR(255)");
            echo "Spalte '$column' erfolgreich hinzugefügt in Tabelle '$settings_table'.<br>";
        }
    }

    // Beispiel: Aktualisieren der globalen Einstellungen
    $stmt = $conn->prepare("REPLACE INTO $settings_table (name, value) VALUES (:name, :value)");
    $stmt->execute([':name' => 'repeat_interval_correct', ':value' => 7]);
    $stmt->execute([':name' => 'repeat_interval_incorrect', ':value' => 1]);
    echo "Globale Intervalle erfolgreich aktualisiert (in die Datenbank geschrieben).<br>";


    // Tabelle für benutzerspezifische Einstellungen (falls noch nicht vorhanden)
    $users_table = 'users';
    $columns_usr = ['repeat_interval_correct', 'repeat_interval_incorrect'];
    foreach ($columns_usr as $column_usr) {
        $stmt = $conn->prepare("SHOW COLUMNS FROM $users_table LIKE '$column_usr'");
        $stmt->execute();
        if ($stmt->rowCount() === 0) {
            $conn->exec("ALTER TABLE $users_table ADD COLUMN $column_usr INT");
            echo "Spalte '$column_usr' erfolgreich hinzugefügt in Tabelle '$users_table'.<br>";
        }
    }

} catch (PDOException $e) {
    echo "Fehler: " . $e->getMessage();
}
