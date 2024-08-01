<?php
// Bestimmen Sie den Pfad zur Logout-Seite basierend auf dem aktuellen Verzeichnis
$logoutPath = (basename(dirname(__FILE__)) === 'admin') ? '../logout.php' : 'logout.php';
?>

<footer class="footer">
    <div class="container text-center">
        <?php if (isset($_SESSION['user_id'])): ?>
            <div class="mb-2">
            <a href="<?php echo $logoutPath; ?>" class="btn btn-sm btn-outline-secondary">
                    <i class="fas fa-sign-out-alt"></i> Logout
                </a>
                <br>
                <span class="username mr-3">
                    <p>angemeldet als <?php echo htmlspecialchars($_SESSION['username']); ?></p>
                </span>
            </div>
        <?php endif; ?>
        <p class="footer">Entwickelt mit ❤️ für interaktives Lernen und Wissensüberprüfung</p>
    </div>
</footer>