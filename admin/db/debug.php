<?php
// debug_db.php
require_once '../../config.php';

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
// Session starten
session_start();

// Überprüfen, ob der Benutzer angemeldet ist und die Rolle Admin hat
checkUserRole(ROLE_ADMIN);

try {
    $db = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME, DB_USER, DB_PASS);
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Alle Tabellen auflisten
    $stmt = $db->query("SHOW TABLES");
    $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);

    echo "<div class='container mt-5'>";
    echo "<h1 class='text-center mb-4'>Datenbank-Debug</h1>";

    echo "<div class='list-group mb-4'>";
    foreach ($tables as $table) {
        echo "<a href='#$table' class='list-group-item list-group-item-action'>$table</a>";
    }
    echo "</div>";

    foreach ($tables as $table) {
        echo "<h2 id='$table' class='mt-4'>$table</h2>";
        $stmt = $db->query("SELECT * FROM $table");
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if (count($rows) > 0) {
            echo "<div class='table-responsive'>";
            echo "<table id='table_$table' class='table table-bordered display'>";
            echo "<thead class='thead-dark'>";
            echo "<tr>";
            foreach ($rows[0] as $key => $value) {
                echo "<th scope='col'>$key</th>";
            }
            echo "</tr>";
            echo "</thead>";
            echo "<tbody>";
            foreach ($rows as $row) {
                echo "<tr>";
                foreach ($row as $value) {
                    echo "<td>" . htmlspecialchars($value) . "</td>";
                }
                echo "</tr>";
            }
            echo "</tbody>";
            echo "</table>";
            echo "</div>";
        } else {
            echo "<p>Keine Daten in dieser Tabelle.</p>";
        }
        echo "<br>";
    }

    echo "</div>";

} catch(PDOException $e) {
    echo "<div class='alert alert-danger'>Fehler: " . $e->getMessage() . "</div>";
}

// Am Ende der debug_db.php hinzufügen
$stmt = $db->prepare("UPDATE users SET role = 'admin' WHERE username = ?");
$stmt->execute(['IhrAdminBenutzername']);
echo "<div class='alert alert-success'>Admin-Rolle wurde aktualisiert.</div>";
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Datenbank-Debug</title>
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/1.10.21/css/jquery.dataTables.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css">
    <style>
        body {
            background-color: #f8f9fa;
        }
        h1, h2 {
            color: #343a40;
        }
        table {
            margin-bottom: 2rem;
        }
        .list-group-item {
            cursor: pointer;
        }
        #backToTop {
    position: fixed;
    bottom: 20px;
    right: 20px;
    display: none;
    z-index: 99;
    opacity: 0.7;
    transition: opacity 0.3s;
}

#backToTop:hover {
    opacity: 1;
}

    </style>
</head>
<body>
<button id="backToTop" title="Zurück nach oben" class="btn btn-primary btn-sm" style="display: none;">
    <i class="fas fa-arrow-up"></i>
</button>
    <script src="https://code.jquery.com/jquery-3.5.1.min.js"></script>
    <script src="https://cdn.datatables.net/1.10.21/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.5.3/dist/umd/popper.min.js"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>
    <script>
        $(document).ready(function() {
            $('table.display').DataTable();
        });
    </script>
    <script>
    // Back to Top Button Funktionalität
    var backToTopButton = document.getElementById("backToTop");

    window.onscroll = function() {scrollFunction()};

    function scrollFunction() {
        if (document.body.scrollTop > 20 || document.documentElement.scrollTop > 20) {
            backToTopButton.style.display = "block";
        } else {
            backToTopButton.style.display = "none";
        }
    }

    backToTopButton.onclick = function() {
        document.body.scrollTop = 0; // Für Safari
        document.documentElement.scrollTop = 0; // Für Chrome, Firefox, IE und Opera
    }
</script>
</body>
</html>
