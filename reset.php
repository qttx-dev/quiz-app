<?php
session_start();
require_once 'config.php';
checkUserRole(ROLE_USER);
?>

<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Statistik zurücksetzen - Bestätigung</title>
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
</head>
<body>
    <div class="container mt-5">
        <h1>Statistik zurücksetzen</h1>
        <p>Sind Sie sicher, dass Sie Ihre Statistik zurücksetzen möchten? Diese Aktion kann nicht rückgängig gemacht werden.</p>
        <p>Ihre bisher gegebenen Antworten werden nicht mehr berücksichtigt.</p>
        <form action="reset_statistics.php" method="post">
            <button type="submit" name="confirm_reset" class="btn btn-danger">Ja, Statistik zurücksetzen</button>
            <a href="index.php" class="btn btn-secondary">Abbrechen</a>
        </form>
    </div>
</body>
</html>
