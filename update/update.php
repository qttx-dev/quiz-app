<?php
// Einbinden der Konfigurationsdatei
require_once '../config.php';

// Überprüfen, ob der Benutzer angemeldet ist und die Rolle Admin hat
checkUserRole(ROLE_ADMIN);

// Funktion zum Ausgeben von Fortschrittsmeldungen
function updateProgress($message) {
    echo json_encode(['message' => $message]);
    ob_flush();
    flush();
}

// Funktion zum Rollback
function rollback($backupFilename, $originalFilename) {
    if (file_exists($backupFilename)) {
        if (copy($backupFilename, $originalFilename)) {
            unlink($backupFilename);
            return "Rollback erfolgreich durchgeführt. Die ursprüngliche Version wurde wiederhergestellt.";
        } else {
            return "Fehler beim Rollback. Bitte stellen Sie die Datei manuell wieder her.";
        }
    } else {
        return "Keine Backup-Datei gefunden. Rollback nicht möglich.";
    }
}

// Funktion zum Überprüfen des Datenbankschemas
function checkDatabaseSchema($conn) {
    $stmt = $conn->prepare("SHOW COLUMNS FROM user_statistics LIKE 'view_count'");
    $stmt->execute();
    $viewCountExists = $stmt->rowCount() > 0;

    $stmt = $conn->prepare("SHOW COLUMNS FROM user_statistics LIKE 'last_shown'");
    $stmt->execute();
    $lastShownExists = $stmt->rowCount() > 0;

    return ['view_count' => $viewCountExists, 'last_shown' => $lastShownExists];
}

// Funktion zum Überprüfen der Funktionen in quiz.php
function checkQuizFunctions($filename) {
    $content = file_get_contents($filename);
    $getQuestionsUpdated = strpos($content, 'COALESCE(us.view_count, 0) as view_count') !== false 
                           && strpos($content, 'COALESCE(us.last_shown, \'1970-01-01\') as last_shown') !== false;
    $updateUserStatisticsUpdated = strpos($content, 'view_count = view_count + 1') !== false 
                                   && strpos($content, 'last_shown = NOW()') !== false;

    return ['getQuestions' => $getQuestionsUpdated, 'updateUserStatistics' => $updateUserStatisticsUpdated];
}

// HTML-Ausgabe für die Warnmeldung und den Start-Button
if (!isset($_POST['action'])) {
    $conn = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME, DB_USER, DB_PASS);
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    $dbStatus = checkDatabaseSchema($conn);
    $quizStatus = checkQuizFunctions('../quiz.php');

    echo '<!DOCTYPE html>
    <html lang="de">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Update Status</title>
        <style>
            body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; max-width: 800px; margin: 0 auto; padding: 20px; }
            .warning { background-color: #fff3cd; border: 1px solid #ffeeba; color: #856404; padding: 15px; border-radius: 5px; margin-bottom: 20px; }
            .btn { display: inline-block; background-color: #007bff; color: #fff; padding: 10px 20px; text-decoration: none; border-radius: 5px; }
            #progressBar { width: 0%; height: 20px; background-color: #4CAF50; text-align: center; line-height: 20px; color: white; }
            .status-item { margin-bottom: 10px; }
            .status-done { color: green; }
            .status-pending { color: orange; }
        </style>
        <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
        <script>
        $(document).ready(function() {
            let currentStep = 0;
            const totalSteps = 5;

            function updateProgressBar(step) {
                let percent = (step / totalSteps) * 100;
                $("#progressBar").css("width", percent + "%").text(percent + "%");
            }

            function performStep(action) {
                $.ajax({
                    url: "update.php",
                    method: "POST",
                    data: { action: action },
                    dataType: "json",
                    success: function(response) {
                        $("#progressMessages").append("<p>" + response.message + "</p>");
                        if (response.status === "error") {
                            alert("Ein Fehler ist aufgetreten: " + response.message);
                        } else if (response.status === "complete") {
                            updateProgressBar(totalSteps);
                            $("#progressMessages").append("<p>Update abgeschlossen!</p>");
                        } else {
                            currentStep++;
                            updateProgressBar(currentStep);
                            nextStep();
                        }
                    },
                    error: function(jqXHR, textStatus, errorThrown) {
                        handleError("Ein Fehler ist aufgetreten: " + textStatus + " - " + errorThrown);
                    }
                });
            }

            function nextStep() {
                switch(currentStep) {
                    case 1: performStep("alter_table"); break;
                    case 2: performStep("backup_file"); break;
                    case 3: performStep("update_get_questions"); break;
                    case 4: performStep("update_user_statistics"); break;
                    case 5: performStep("finish"); break;
                }
            }

            function handleError(message) {
                $("#progressMessages").append("<p style=\'color: red;\'>Fehler: " + message + "</p>");
                $("#startUpdate").prop("disabled", false).text("Update neu starten");
            }

            $("#startUpdate").click(function() {
                $(this).prop("disabled", true);
                performStep("start");
            });
        });
        </script>
    </head>
    <body>
        <h1>Update Status</h1>
        <div class="status-item">
            Datenbank Spalte "view_count": 
            <span class="' . ($dbStatus['view_count'] ? 'status-done">Vorhanden' : 'status-pending">Ausstehend') . '</span>
        </div>
        <div class="status-item">
            Datenbank Spalte "last_shown": 
            <span class="' . ($dbStatus['last_shown'] ? 'status-done">Vorhanden' : 'status-pending">Ausstehend') . '</span>
        </div>
        <div class="status-item">
            Funktion "getQuestions": 
            <span class="' . ($quizStatus['getQuestions'] ? 'status-done">Aktualisiert' : 'status-pending">Ausstehend') . '</span>
        </div>
        <div class="status-item">
            Funktion "updateUserStatistics": 
            <span class="' . ($quizStatus['updateUserStatistics'] ? 'status-done">Aktualisiert' : 'status-pending">Ausstehend') . '</span>
        </div>
        <div class="warning">
            <h2>Achtung: Update-Prozess</h2>
            <p>Sie sind dabei, ein Update durchzuführen. Bitte beachten Sie:</p>
            <ul>
                <li>Es wird automatisch ein Backup der zu ändernden Dateien erstellt.</li>
                <li>Sie können im Fehlerfall zu der vorherigen Version zurückkehren.</li>
                <li>Es wird dringend empfohlen, vor dem Update ein eigenes Backup Ihrer Datenbank und Dateien zu erstellen.</li>
                <li>Nach erfolgreichem Update können Sie die erstellten Backup-Dateien löschen.</li>
                <li>Der Update-Prozess kann einige Momente dauern. Bitte haben Sie Geduld und schließen Sie den Browser nicht.</li>
            </ul>
        </div>
        <button id="startUpdate" class="btn">Update starten</button>
        <div id="progressBar"></div>
        <div id="progressMessages"></div>
    </body>
    </html>';
    exit;
}

// Hauptupdate-Prozess
if (isset($_POST['action'])) {
    try {
        $conn = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME, DB_USER, DB_PASS);
        $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        switch ($_POST['action']) {
            case 'start':
                echo json_encode(['status' => 'success', 'message' => "Update gestartet"]);
                break;

            case 'alter_table':
                $dbStatus = checkDatabaseSchema($conn);
                if (!$dbStatus['view_count']) {
                    $conn->exec("ALTER TABLE user_statistics ADD COLUMN view_count INT DEFAULT 0");
                }
                if (!$dbStatus['last_shown']) {
                    $conn->exec("ALTER TABLE user_statistics ADD COLUMN last_shown DATETIME");
                }
                echo json_encode(['status' => 'success', 'message' => "Tabelle aktualisiert"]);
                break;

            case 'backup_file':
                $filename = '../quiz.php';
                $backupFilename = '../quiz_backup.php';
                if (!file_exists($backupFilename) && copy($filename, $backupFilename)) {
                    echo json_encode(['status' => 'success', 'message' => "Backup erstellt"]);
                } else {
                    echo json_encode(['status' => 'success', 'message' => "Backup bereits vorhanden oder nicht erforderlich"]);
                }
                break;

            case 'update_get_questions':
                $quizStatus = checkQuizFunctions('../quiz.php');
                if (!$quizStatus['getQuestions']) {
                    $newFunction = '
                    function getQuestions($db, $category_ids, $limit) {
                        $placeholders = implode(",", array_fill(0, count($category_ids), "?"));
                    
                        $sql = "SELECT q.id, q.question_text, 
                           COALESCE(us.correct_count, 0) as correct_count, 
                           COALESCE(us.incorrect_count, 0) as incorrect_count,
                           COALESCE(us.view_count, 0) as view_count,
                           COALESCE(us.last_shown, \'1970-01-01\') as last_shown
                    FROM questions q
                    JOIN question_categories qc ON q.id = qc.question_id
                    LEFT JOIN user_statistics us ON q.id = us.question_id AND us.user_id = ?
                    WHERE qc.category_id IN ($placeholders)
                    GROUP BY q.id
                    HAVING (view_count = 0) OR 
                           (incorrect_count > correct_count AND DATEDIFF(NOW(), last_shown) >= 1) OR
                           (correct_count >= incorrect_count AND DATEDIFF(NOW(), last_shown) >= 7)
                    ORDER BY 
                        CASE 
                            WHEN view_count = 0 THEN 0
                            WHEN incorrect_count > correct_count THEN 1
                            ELSE 2
                        END,
                        (COALESCE(us.incorrect_count, 0) - COALESCE(us.correct_count, 0)) DESC,
                        view_count ASC,
                        RAND()
                    LIMIT ?";
                    
                        $stmt = $db->prepare($sql);
                        $params = array_merge([$_SESSION[\'user_id\']], $category_ids, [$limit]);
                        $stmt->execute($params);
                        return $stmt->fetchAll(PDO::FETCH_ASSOC);
                    }
                    ';
                    
                    $content = file_get_contents('../quiz.php');
                    $pattern = '/function getQuestions\([^{]+{[\s\S]+?}/';
                    $content = preg_replace($pattern, $newFunction, $content);
                    if (file_put_contents('../quiz.php', $content) === false) {
                        throw new Exception("Fehler beim Schreiben der aktualisierten quiz.php");
                    }
                    echo json_encode(['status' => 'success', 'message' => "getQuestions Funktion aktualisiert"]);
                } else {
                    echo json_encode(['status' => 'success', 'message' => "getQuestions Funktion bereits aktuell"]);
                }
                break;

            case 'update_user_statistics':
                $quizStatus = checkQuizFunctions('../quiz.php');
                if (!$quizStatus['updateUserStatistics']) {
                    $newFunction = '
                    function updateUserStatistics($db, $user_id, $question_id, $is_correct = null) {
                        $stmt = $db->prepare("INSERT INTO user_statistics (user_id, question_id, correct_count, incorrect_count, view_count, last_shown) 
                                              VALUES (?, ?, 0, 0, 1, NOW()) 
                                              ON DUPLICATE KEY UPDATE 
                                              correct_count = correct_count + ?,
                                              incorrect_count = incorrect_count + ?,
                                              view_count = view_count + 1,
                                              last_shown = NOW()");
                        
                        if ($is_correct === null) {
                            // Frage wurde nur angezeigt, aber noch nicht beantwortet
                            $correct_increment = 0;
                            $incorrect_increment = 0;
                        } else {
                            $correct_increment = $is_correct ? 1 : 0;
                            $incorrect_increment = $is_correct ? 0 : 1;
                        }
                        
                        $stmt->execute([$user_id, $question_id, $correct_increment, $incorrect_increment]);
                    }
                    ';
                    
                    $content = file_get_contents('../quiz.php');
                    $pattern = '/function updateUserStatistics\([^{]+{[\s\S]+?}/';
                    $content = preg_replace($pattern, $newFunction, $content);
                    if (file_put_contents('../quiz.php', $content) === false) {
                        throw new Exception("Fehler beim Schreiben der aktualisierten quiz.php");
                    }
                    echo json_encode(['status' => 'success', 'message' => "updateUserStatistics Funktion aktualisiert"]);
                } else {
                    echo json_encode(['status' => 'success', 'message' => "updateUserStatistics Funktion bereits aktuell"]);
                }
                break;

            case 'finish':
                echo json_encode(['status' => 'complete', 'message' => "Update abgeschlossen"]);
                break;

            default:
                throw new Exception("Unbekannte Aktion");
        }
    } catch(Exception $e) {
        echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
    }
    exit;
}
?>
