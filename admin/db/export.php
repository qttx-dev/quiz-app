<?php
require_once '../../config.php';

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Session starten
session_start();

// Überprüfen, ob der Benutzer angemeldet ist und die Rolle Admin hat
if (!isset($_SESSION['user_role']) || $_SESSION['user_role'] !== ROLE_ADMIN) {
    die("Zugriff verweigert. Sie haben keine Berechtigung, diese Seite zu sehen.");
}

// Funktion zum Escapen von Werten
function escape($value) {
    return str_replace(array("\\", "'", "\r", "\n"), array("\\\\", "\\'", "\\r", "\\n"), $value);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Dateiname für den Dump
    $filename = 'database_dump_' . date('Y-m-d_H-i-s') . '.sql';

    // Header setzen, um den Download zu erzwingen
    header('Content-Type: application/octet-stream');
    header('Content-Disposition: attachment; filename="' . $filename . '"');

    // Tabellen auflisten
    $tables = array();
    $stmt = $db->query("SHOW TABLES");
    while ($row = $stmt->fetch(PDO::FETCH_NUM)) {
        $tables[] = $row[0];
    }

    // Dump generieren
    foreach ($tables as $table) {
        // Tabellenstruktur
        $stmt = $db->query('SHOW CREATE TABLE ' . $table);
        $row = $stmt->fetch(PDO::FETCH_NUM);
        echo "DROP TABLE IF EXISTS `$table`;\n\n";
        echo $row[1] . ";\n\n";

        // Tabellendaten
        $stmt = $db->query('SELECT * FROM ' . $table);
        $num_fields = $stmt->columnCount();

        while ($row = $stmt->fetch(PDO::FETCH_NUM)) {
            echo "INSERT INTO `$table` VALUES(";
            for ($j = 0; $j < $num_fields; $j++) {
                echo "'" . escape($row[$j]) . "'";
                if ($j < ($num_fields-1)) {
                    echo ',';
                }
            }
            echo ");\n";
        }
        echo "\n";
    }
    exit;
}
?>

<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Datenbank-Export</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            background-color: #f4f4f4;
            margin: 0;
            padding: 0;
        }
        .container {
            max-width: 600px;
            margin: 50px auto;
            padding: 20px;
            background-color: #fff;
            box-shadow: 0 0 10px rgba(0, 0, 0, 0.1);
            border-radius: 8px;
        }
        h2 {
            text-align: center;
            color: #333;
        }
        form {
            text-align: center;
            margin-top: 20px;
        }
        button {
            background-color: #007bff;
            color: white;
            border: none;
            padding: 10px 20px;
            font-size: 16px;
            cursor: pointer;
            border-radius: 5px;
        }
        button:hover {
            background-color: #0056b3;
        }
    </style>
</head>
<body>
    <div class="container">
        <h2>Datenbank-Export</h2>
        <form method="post" action="">
            <button type="submit">Datenbank exportieren</button>
        </form>
    </div>
</body>
</html>