<?php
session_start();
require_once '../config.php';

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

checkUserRole(ROLE_ADMIN);
?>

<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Panel - Quiz App</title>
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
</head>
<body>
    <div class="container mt-5">
        <h1 class="text-center mb-4">Admin Panel</h1>
        <div class="list-group">
            <a href="manage_questions.php" class="list-group-item list-group-item-action">Fragen verwalten</a>
            <a href="manage_categories.php" class="list-group-item list-group-item-action">Kategorien verwalten</a>
            <a href="user_management.php" class="list-group-item list-group-item-action">Benutzerverwaltung</a>
            <a href="settings.php" class="list-group-item list-group-item-action">Einstellungen</a>
        </div>
    </div>

    <?php include '../footer.php'; ?>
</body>
</html>
