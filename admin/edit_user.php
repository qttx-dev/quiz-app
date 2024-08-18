<?php
session_start();
require_once '../config.php';

checkUserRole(ROLE_ADMIN);

$userId = isset($_GET['id']) ? intval($_GET['id']) : 0;

if (!$userId) {
    header("Location: user_management.php");
    exit;
}

$stmt = $db->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$userId]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$user) {
    header("Location: user_management.php");
    exit;
}

// Holen Sie alle verfügbaren Kategorien
$stmtCategories = $db->query("SELECT * FROM categories");
$categories = $stmtCategories->fetchAll(PDO::FETCH_ASSOC);

// Überprüfen Sie, ob die user_categories Tabelle existiert
$stmt = $db->query("SHOW TABLES LIKE 'user_categories'");
$tableExists = $stmt->rowCount() > 0;

$userCategories = [];
if ($tableExists) {
    // Holen Sie die Kategorien, auf die der Benutzer Zugriff hat
    $stmtUserCategories = $db->prepare("SELECT category_id FROM user_categories WHERE user_id = ?");
    $stmtUserCategories->execute([$userId]);
    $userCategories = $stmtUserCategories->fetchAll(PDO::FETCH_COLUMN);
}

?>

<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Benutzer bearbeiten - Quiz App</title>
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css">
</head>
<body>
    <div class="container mt-5">
        <h1 class="mb-4">Benutzer bearbeiten</h1>
        <form action="update_user.php" method="post">
            <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
            <div class="form-group">
                <label for="username">Benutzername</label>
                <input type="text" class="form-control" id="username" name="username" value="<?php echo htmlspecialchars($user['username']); ?>" required>
            </div>
            <div class="form-group">
                <label for="email">E-Mail</label>
                <input type="email" class="form-control" id="email" name="email" value="<?php echo htmlspecialchars($user['email']); ?>" required>
            </div>
            <div class="form-group">
                <label for="role">Rolle</label>
                <select class="form-control" id="role" name="role">
                    <option value="user" <?php echo $user['role'] == 'user' ? 'selected' : ''; ?>>Benutzer</option>
                    <option value="editor" <?php echo $user['role'] == 'editor' ? 'selected' : ''; ?>>Editor</option>
                    <option value="manager" <?php echo $user['role'] == 'manager' ? 'selected' : ''; ?>>Manager</option>
                    <option value="admin" <?php echo $user['role'] == 'admin' ? 'selected' : ''; ?>>Admin</option>
                </select>
            </div>
            <div class="form-group">
                <label for="repeat_interval_correct">Wiederholungsintervall bei korrekter Antwort (Tage)</label>
                <input type="number" class="form-control" id="repeat_interval_correct" name="repeat_interval_correct" value="<?php echo htmlspecialchars($user['repeat_interval_correct'] ?? '7'); ?>" required>
            </div>
            <div class="form-group">
                <label for="repeat_interval_incorrect">Wiederholungsintervall bei falscher Antwort (Tage)</label>
                <input type="number" class="form-control" id="repeat_interval_incorrect" name="repeat_interval_incorrect" value="<?php echo htmlspecialchars($user['repeat_interval_incorrect'] ?? '1'); ?>" required>
            </div>
            <?php if ($tableExists && !empty($categories)): ?>
                <div class="form-group">
                    <label>Kategorien</label>
                    <?php foreach ($categories as $category): ?>
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" name="categories[]" value="<?php echo $category['id']; ?>" id="category_<?php echo $category['id']; ?>"
                                <?php echo in_array($category['id'], $userCategories) ? 'checked' : ''; ?>>
                            <label class="form-check-label" for="category_<?php echo $category['id']; ?>">
                                <?php echo htmlspecialchars($category['name']); ?>
                            </label>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
            <button type="submit" class="btn btn-primary">Speichern</button>
            <a href="user_management.php" class="btn btn-secondary">Abbrechen</a>
        </form>
    </div>

    <?php include '../footer.php'; ?>

    <script src="https://code.jquery.com/jquery-3.5.1.slim.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.5.3/dist/umd/popper.min.js"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>
</body>
</html>
