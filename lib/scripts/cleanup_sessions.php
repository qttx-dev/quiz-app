<?php
// Überprüfen, ob das Skript von der Kommandozeile (CLI) aufgerufen wird
if (php_sapi_name() !== 'cli') {
    die("Dieses Skript kann nur von der Kommandozeile aus aufgerufen werden.");
}

// Absoluter Pfad zur config.php Datei
require __DIR__ . '/../config.php';

try {
    $db = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME, DB_USER, DB_PASS);
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Aktualisiere Benutzer, deren Session abgelaufen ist (z.B. nach 30 Minuten Inaktivität)
    $update_stmt = $db->prepare("
        UPDATE users 
        SET logged_in = FALSE, logged_out = NOW() 
        WHERE logged_in = TRUE AND last_login < DATE_SUB(NOW(), INTERVAL 30 MINUTE)
    ");
    $update_stmt->execute();

    echo "Session cleanup completed.";
} catch (PDOException $e) {
    error_log("Fehler beim Bereinigen der Sessions: " . $e->getMessage());
}
?>
