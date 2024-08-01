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

$message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_FILES['sql_file']) && $_FILES['sql_file']['error'] == 0) {
        $filename = $_FILES['sql_file']['tmp_name'];
        $handle = fopen($filename, "r+");
        $contents = fread($handle, filesize($filename));
        $sql = explode(';', $contents);
        fclose($handle);

        try {
            $db->beginTransaction();

            foreach($sql as $query) {
                $query = trim($query);
                if (!empty($query)) {
                    $db->exec($query);
                }
            }

            $db->commit();
            $message = "Import erfolgreich abgeschlossen.";
        } catch (PDOException $e) {
            $db->rollBack();
            $message = "Fehler beim Ausführen der Abfrage: " . $e->getMessage();
        }
    } else {
        $message = "Fehler beim Hochladen der Datei.";
    }
}
?>

<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Datenbank-Import</title>
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
        input[type="file"] {
            margin-bottom: 10px;
        }
        input[type="submit"] {
            background-color: #007bff;
            color: white;
            border: none;
            padding: 10px 20px;
            font-size: 16px;
            cursor: pointer;
            border-radius: 5px;
        }
        input[type="submit"]:hover {
            background-color: #0056b3;
        }
        .message {
            margin-top: 20px;
            padding: 10px;
            border-radius: 5px;
            text-align: center;
        }
        .success {
            background-color: #d4edda;
            color: #155724;
        }
        .error {
            background-color: #f8d7da;
            color: #721c24;
        }
    </style>
</head>
<body>
    <div class="container">
        <h2>SQL-Datei importieren</h2>
        <?php if ($message): ?>
            <div class="message <?php echo strpos($message, 'erfolgreich') !== false ? 'success' : 'error'; ?>">
                <?php echo $message; ?>
            </div>
        <?php endif; ?>
        <form action="" method="post" enctype="multipart/form-data">
            <input type="file" name="sql_file" accept=".sql">
            <br>
            <input type="submit" value="Importieren">
        </form>
    </div>
</body>
</html>