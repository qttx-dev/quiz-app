<?php
session_start();
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

$message = '';
$step = 1;

// Funktion zum Überprüfen der Schreibrechte
function check_permissions($file, $required_perms) {
    if (!file_exists($file)) {
        return is_writable(dirname($file));
    }
    $current_perms = fileperms($file) & 0777;
    return $current_perms == $required_perms;
}

// Funktion zum Überprüfen, ob PHPMailer installiert ist
function check_phpmailer() {
    return file_exists('vendor/phpmailer/phpmailer/src/PHPMailer.php');
}

// Überprüfen der Verzeichnisrechte
$setup_permissions = check_permissions(__FILE__, 0666);
$directory_permissions = check_permissions('.', 0755);
$directory_writable = is_writable('.');
$phpmailer_installed = check_phpmailer();

// Überprüfen der anderen PHP-Dateien im Verzeichnis
$other_files_permissions = true;
$files = glob('*.php');
foreach ($files as $file) {
    if ($file != basename(__FILE__)) {
        if (!check_permissions($file, 0644)) {
            $other_files_permissions = false;
            break;
        }
    }
}

$all_permissions_correct = $setup_permissions && $directory_permissions && $other_files_permissions && $directory_writable && $phpmailer_installed;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['step']) && $_POST['step'] == 1 && $all_permissions_correct) {
        // Schritt 1: Datenbankverbindung speichern
        $_SESSION['db_config'] = [
            'host' => $_POST['db_host'],
            'name' => $_POST['db_name'],
            'user' => $_POST['db_user'],
            'pass' => $_POST['db_pass']
        ];
        
        $step = 2;
    } elseif (isset($_POST['step']) && $_POST['step'] == 2) {
        // Schritt 2: E-Mail-Server-Einstellungen konfigurieren und Admin-Benutzer erstellen
        try {
            // Verbindung zur Datenbank herstellen
            $db = new PDO("mysql:host={$_SESSION['db_config']['host']};dbname={$_SESSION['db_config']['name']}", $_SESSION['db_config']['user'], $_SESSION['db_config']['pass']);
            $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

            // SQL für Tabellenerstellung
            $sql = "
            CREATE TABLE IF NOT EXISTS users (
                id INT AUTO_INCREMENT PRIMARY KEY,
                username VARCHAR(50) UNIQUE NOT NULL,
                email VARCHAR(100) UNIQUE NOT NULL,
                password VARCHAR(255) NOT NULL,
                role ENUM('user', 'editor', 'manager', 'admin') NOT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                last_login DATETIME DEFAULT NULL,
                loggend_out DATETIME DEFAULT NULL,
                logged_in BOOLEAN DEFAULT FALSE,
                reset_token VARCHAR(64) DEFAULT NULL,
                reset_token_expiry DATETIME DEFAULT NULL
            );

            CREATE TABLE IF NOT EXISTS settings (
                name VARCHAR(50) PRIMARY KEY,
                value TEXT NOT NULL
            );

            CREATE TABLE IF NOT EXISTS categories (
                id INT AUTO_INCREMENT PRIMARY KEY,
                name VARCHAR(255) NOT NULL
            );

            CREATE TABLE IF NOT EXISTS questions (
                id INT AUTO_INCREMENT PRIMARY KEY,
                question_text TEXT NOT NULL,
                explanation TEXT
            );

            CREATE TABLE IF NOT EXISTS answers (
                id INT AUTO_INCREMENT PRIMARY KEY,
                question_id INT NOT NULL,
                answer_text TEXT NOT NULL,
                is_correct TINYINT(1) NOT NULL,
                FOREIGN KEY (question_id) REFERENCES questions(id) ON DELETE CASCADE
            );

            CREATE TABLE IF NOT EXISTS question_categories (
                question_id INT NOT NULL,
                category_id INT NOT NULL,
                PRIMARY KEY (question_id, category_id),
                FOREIGN KEY (question_id) REFERENCES questions(id) ON DELETE CASCADE,
                FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE CASCADE
            );

            CREATE TABLE user_statistics (
                id INT AUTO_INCREMENT PRIMARY KEY,
                user_id INT,
                question_id INT,
                correct_count INT DEFAULT 0,
                incorrect_count INT DEFAULT 0,
                view_count INT DEFAULT 0,
                last_shown DATETIME,
                FOREIGN KEY (user_id) REFERENCES users(id),
                FOREIGN KEY (question_id) REFERENCES questions(id)
            );

            INSERT INTO settings (name, value) VALUES ('allow_self_registration', '1');
            ";

            $db->exec($sql);

// Admin-Benutzer erstellen
$username = $_POST['admin_username'];
$email = $_POST['admin_email'];
$password = password_hash($_POST['admin_password'], PASSWORD_BCRYPT);

// Fügen Sie die Rolle ROLE_ADMIN hinzu
$stmt = $db->prepare("INSERT INTO users (username, email, password, role) VALUES (?, ?, ?, 'admin')");
$stmt->execute([$username, $email, $password]);

// Erstellen der config.php
$config_content = '<?php
define(\'DB_HOST\', \'' . $_SESSION['db_config']['host'] . '\');
define(\'DB_NAME\', \'' . $_SESSION['db_config']['name'] . '\');
define(\'DB_USER\', \'' . $_SESSION['db_config']['user'] . '\');
define(\'DB_PASS\', \'' . $_SESSION['db_config']['pass'] . '\');

try {
    $db = new PDO(
        \'mysql:host=\' . DB_HOST . \';dbname=\' . DB_NAME . \';charset=utf8mb4\',
        DB_USER,
        DB_PASS,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]
    );
} catch (PDOException $e) {
    die(\'Verbindung zur Datenbank fehlgeschlagen: \' . $e->getMessage());
}

define(\'SMTP_HOST\', \'' . $_POST['smtp_host'] . '\');
define(\'SMTP_PORT\', ' . $_POST['smtp_port'] . ');
define(\'SMTP_USER\', \'' . $_POST['smtp_user'] . '\');
define(\'SMTP_PASS\', \'' . $_POST['smtp_pass'] . '\');
define(\'SMTP_FROM\', \'' . $_POST['smtp_user'] . '\');
define(\'SMTP_FROM_NAME\', \'Quiz App\');
define(\'SMTP_SECURE\', \'tls\');

define(\'ROLE_USER\', \'user\');
define(\'ROLE_EDITOR\', \'editor\');
define(\'ROLE_MANAGER\', \'manager\');
define(\'ROLE_ADMIN\', \'admin\');

function checkUserRole($requiredRole) {
    if (!isset($_SESSION[\'user_role\'])) {
        header(\'Location: login.php\');
        exit();
    }

    $userRole = $_SESSION[\'user_role\'];
    $roles = [ROLE_USER, ROLE_EDITOR, ROLE_MANAGER, ROLE_ADMIN];
    $userRoleIndex = array_search($userRole, $roles);
    $requiredRoleIndex = array_search($requiredRole, $roles);

    if ($userRoleIndex === false || ($userRoleIndex < $requiredRoleIndex && $userRole !== ROLE_ADMIN)) {
        header(\'Location: index.php\');
        exit();
    }
}
?>';

            file_put_contents('config.php', $config_content);

            $message = "Setup erfolgreich abgeschlossen. Bitte überprüfen Sie die Dateirechte und löschen oder benennen Sie die setup.php aus Sicherheitsgründen um.";
            $step = 3; // Setup abgeschlossen
        } catch (Exception $e) {
            $message = "Fehler beim Setup: " . $e->getMessage();
        }
    }
}

// Überprüfung der finalen Sicherheitseinstellungen
if ($step == 3) {
    $final_config_permissions = check_permissions('config.php', 0644);
    $final_directory_permissions = check_permissions('.', 0755);
    $final_setup_permissions = check_permissions(__FILE__, 0666);
    $security_warning = '';
    
    if (!$final_config_permissions) {
        $security_warning .= "Die Dateirechte für config.php sind nicht korrekt (sollten 644 sein). ";
    }
    if (!$final_directory_permissions) {
        $security_warning .= "Die Verzeichnisrechte sind nicht korrekt (sollten 755 sein). ";
    }
    if (!$final_setup_permissions) {
        $security_warning .= "Die Dateirechte für setup.php sind nicht korrekt (sollten 666 sein). ";
    }
    
    if ($security_warning) {
        $message .= " SICHERHEITSWARNUNG: " . $security_warning . "Bitte korrigieren Sie dies manuell.";
    } else {
        $message .= " Alle Sicherheitseinstellungen sind korrekt. Bitte löschen oder benennen Sie die setup.php aus Sicherheitsgründen um.";
    }
}
?>

<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Setup - Quiz App</title>
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css">
</head>
<body>
    <div class="container mt-5">
        <h1 class="text-center mb-4">Setup - Quiz App</h1>
        
        <?php if ($message): ?>
            <div class="alert alert-info"><?php echo $message; ?></div>
        <?php endif; ?>

        <div class="card">
            <div class="card-header">
                <h2><?php echo $step == 1 ? 'Schritt 1: Datenbankverbindung' : 'Schritt 2: E-Mail-Server und Admin-Benutzer'; ?></h2>
            </div>
            <div class="card-body">
                <?php if ($step == 1): ?>
                    <?php if ($all_permissions_correct): ?>
                        <form method="post" action="">
                            <input type="hidden" name="step" value="1">
                            <div class="form-group">
                                <label for="db_host">Datenbank-Host:</label>
                                <input type="text" class="form-control" id="db_host" name="db_host" required>
                            </div>
                            <div class="form-group">
                                <label for="db_name">Datenbank-Name:</label>
                                <input type="text" class="form-control" id="db_name" name="db_name" required>
                            </div>
                            <div class="form-group">
                                <label for="db_user">Datenbank-Benutzer:</label>
                                <input type="text" class="form-control" id="db_user" name="db_user" required>
                            </div>
                            <div class="form-group">
                                <label for="db_pass">Datenbank-Passwort:</label>
                                <input type="password" class="form-control" id="db_pass" name="db_pass" required>
                            </div>
                            <button type="submit" class="btn btn-primary"><i class="fas fa-database"></i> Verbindung testen und fortfahren</button>
                        </form>
                    <?php else: ?>
                        <div class="alert alert-danger">
                            <h4>Überprüfung der Voraussetzungen:</h4>
                            <ul>
                                <li>setup.php: <?php echo $setup_permissions ? '<span class="text-success">Korrekt (666)</span>' : '<span class="text-danger">Inkorrekt (sollte 666 sein)</span>'; ?></li>
                                <li>Verzeichnis: <?php echo $directory_permissions ? '<span class="text-success">Korrekt (755)</span>' : '<span class="text-danger">Inkorrekt (sollte 755 sein)</span>'; ?></li>
                                <li>Verzeichnis beschreibbar: <?php echo $directory_writable ? '<span class="text-success">Ja</span>' : '<span class="text-danger">Nein</span>'; ?></li>
                                <li>PHPMailer: <?php echo $phpmailer_installed ? '<span class="text-success">Installiert</span>' : '<span class="text-danger">Nicht installiert</span>'; ?></li>
                            </ul>
                            <p><strong>Bitte korrigieren Sie die Dateirechte, den Verzeichnisbesitzer und installieren Sie PHPMailer, bevor Sie fortfahren.</strong></p>
                        </div>
                    <?php endif; ?>
                <?php elseif ($step == 2): ?>
                    <form method="post" action="">
                        <input type="hidden" name="step" value="2">
                        <div class="form-group">
                            <label for="smtp_host">SMTP-Host:</label>
                            <input type="text" class="form-control" id="smtp_host" name="smtp_host" required>
                        </div>
                        <div class="form-group">
                            <label for="smtp_port">SMTP-Port:</label>
                            <input type="number" class="form-control" id="smtp_port" name="smtp_port" required value="587">
                        </div>
                        <div class="form-group">
                            <label for="smtp_user">SMTP-Benutzer:</label>
                            <input type="email" class="form-control" id="smtp_user" name="smtp_user" required>
                        </div>
                        <div class="form-group">
                            <label for="smtp_pass">SMTP-Passwort:</label>
                            <input type="password" class="form-control" id="smtp_pass" name="smtp_pass" required>
                        </div>
                        <div class="form-group">
                            <label for="admin_username">Admin-Benutzername:</label>
                            <input type="text" class="form-control" id="admin_username" name="admin_username" required>
                        </div>
                        <div class="form-group">
                            <label for="admin_email">Admin-E-Mail:</label>
                            <input type="email" class="form-control" id="admin_email" name="admin_email" required>
                        </div>
                        <div class="form-group">
                            <label for="admin_password">Admin-Passwort:</label>
                            <input type="password" class="form-control" id="admin_password" name="admin_password" required>
                        </div>
                        <button type="submit" class="btn btn-primary"><i class="fas fa-user-shield"></i> Einstellungen speichern und Admin-Benutzer erstellen</button>
                    </form>
                <?php elseif ($step == 3): ?>
                    <div class="alert alert-success">
                        <h4>Setup abgeschlossen!</h4>
                        <p><?php echo $message; ?></p>
                    </div>
                    <form method="post" action="">
                        <input type="hidden" name="step" value="2">
                        <button type="submit" class="btn btn-secondary"><i class="fas fa-cog"></i> Einstellungen aktualisieren</button>
                    </form>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <footer class="footer">
        <div class="container text-center">
            <p>Entwickelt mit ❤️ für interaktives Lernen und Wissensüberprüfung</p>
        </div>
    </footer>

    <script src="https://code.jquery.com/jquery-3.5.1.slim.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.5.3/dist/umd/popper.min.js"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>
</body>
</html>
