<?php
session_start();
require_once '../config.php';

checkUserRole(ROLE_ADMIN);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $userId = $_POST['user_id'];
    $username = $_POST['username'];
    $email = $_POST['email'];
    $role = $_POST['role'];
    $repeatIntervalCorrect = $_POST['repeat_interval_correct'];
    $repeatIntervalIncorrect = $_POST['repeat_interval_incorrect'];
    $categories = isset($_POST['categories']) ? $_POST['categories'] : [];

    try {
        $db->beginTransaction();

        $stmt = $db->prepare("UPDATE users SET username = ?, email = ?, role = ?, repeat_interval_correct = ?, repeat_interval_incorrect = ? WHERE id = ?");
        $stmt->execute([$username, $email, $role, $repeatIntervalCorrect, $repeatIntervalIncorrect, $userId]);

        // Löschen Sie alle bestehenden Kategorie-Zuordnungen für den Benutzer
        $stmt = $db->prepare("DELETE FROM user_categories WHERE user_id = ?");
        $stmt->execute([$userId]);

        // Fügen Sie die neuen Kategorie-Zuordnungen hinzu
        $stmt = $db->prepare("INSERT INTO user_categories (user_id, category_id) VALUES (?, ?)");
        foreach ($categories as $categoryId) {
            $stmt->execute([$userId, $categoryId]);
        }

        $db->commit();
        $_SESSION['message'] = "Benutzer erfolgreich aktualisiert.";
    } catch (Exception $e) {
        $db->rollBack();
        $_SESSION['error'] = "Fehler beim Aktualisieren des Benutzers: " . $e->getMessage();
    }
}

header("Location: user_management.php");
exit;
