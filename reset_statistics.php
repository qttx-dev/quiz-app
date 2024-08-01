<?php
session_start();
require_once 'config.php';
// Überprüfen, ob der Benutzer angemeldet ist
checkUserRole(ROLE_USER);

if (isset($_SESSION['user_id']) && isset($_POST['confirm_reset'])) {
    $user_id = $_SESSION['user_id'];
    
    $sql = "DELETE FROM user_statistics WHERE user_id = ?";
    $stmt = $db->prepare($sql);
    $stmt->execute([$user_id]);
    
    // Feedback für den Benutzer
    $_SESSION['message'] = "Ihre Statistik wurde erfolgreich zurückgesetzt.";
    header("Location: index.php");
    exit();
} else {
    // Wenn der Benutzer direkt auf diese Seite zugreift, ohne zu bestätigen
    header("Location: reset.php");
    exit();
}
?>
