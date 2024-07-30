<?php
require_once 'config.php';

// Funktion zum Erstellen einer neuen Frage
function createQuestion($db, $questionText, $explanation, $categoryIds, $answers) {
    $db->beginTransaction();
    
    try {
        // Frage einfügen
        $stmt = $db->prepare("INSERT INTO questions (question_text, explanation) VALUES (?, ?)");
        $stmt->execute([$questionText, $explanation]);
        $questionId = $db->lastInsertId();
        
        // Kategorien zuordnen
        $stmt = $db->prepare("INSERT INTO question_categories (question_id, category_id) VALUES (?, ?)");
        foreach ($categoryIds as $categoryId) {
            $stmt->execute([$questionId, $categoryId]);
        }
        
        // Antworten einfügen
        $stmt = $db->prepare("INSERT INTO answers (question_id, answer_text, is_correct) VALUES (?, ?, ?)");
        foreach ($answers as $answer) {
            $stmt->execute([$questionId, $answer['text'], $answer['isCorrect']]);
        }
        
        $db->commit();
        return true;
    } catch (Exception $e) {
        $db->rollBack();
        error_log("Fehler beim Erstellen der Frage: " . $e->getMessage());
        return false;
    }
}

// Funktion zum Abrufen zufälliger Fragen aus bestimmten Kategorien
function getRandomQuestions($db, $categoryIds, $limit) {
    $placeholders = implode(',', array_fill(0, count($categoryIds), '?'));
    
    try {
        $sql = "
            SELECT DISTINCT q.id, q.question_text, q.explanation,
                   GROUP_CONCAT(DISTINCT qc.category_id ORDER BY qc.category_id ASC SEPARATOR ',') AS category_ids,
                   GROUP_CONCAT(a.id, ':::', a.answer_text, ':::', a.is_correct SEPARATOR '|||') as answers
            FROM questions q
            JOIN question_categories qc ON q.id = qc.question_id
            JOIN answers a ON q.id = a.question_id
            WHERE qc.category_id IN ($placeholders)
            GROUP BY q.id
            ORDER BY RAND()
            LIMIT ?
        ";
        
        $stmt = $db->prepare($sql);
        $params = array_merge($categoryIds, [$limit]);
        $stmt->execute($params);
        
        $questions = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Antworten aufbereiten
        foreach ($questions as &$question) {
            $answersRaw = explode('|||', $question['answers']);
            $question['answers'] = [];
            foreach ($answersRaw as $answerRaw) {
                list($id, $text, $isCorrect) = explode(':::', $answerRaw);
                $question['answers'][] = [
                    'id' => $id,
                    'text' => $text,
                    'isCorrect' => $isCorrect
                ];
            }
            unset($question['answers']); // Entfernen des ursprünglichen String-Formats
        }
        
        return $questions;
    } catch (Exception $e) {
        error_log("Fehler beim Abrufen zufälliger Fragen: " . $e->getMessage());
        return [];
    }
}

// Funktion zum Aktualisieren einer bestehenden Frage
function updateQuestion($db, $questionId, $questionText, $explanation, $categoryIds, $answers) {
    $db->beginTransaction();
    
    try {
        // Frage aktualisieren
        $stmt = $db->prepare("UPDATE questions SET question_text = ?, explanation = ? WHERE id = ?");
        $stmt->execute([$questionText, $explanation, $questionId]);
        
        // Bestehende Kategorie-Zuordnungen löschen und neue einfügen
        $stmt = $db->prepare("DELETE FROM question_categories WHERE question_id = ?");
        $stmt->execute([$questionId]);
        
        $stmt = $db->prepare("INSERT INTO question_categories (question_id, category_id) VALUES (?, ?)");
        foreach ($categoryIds as $categoryId) {
            $stmt->execute([$questionId, $categoryId]);
        }
        
        // Bestehende Antworten löschen und neue einfügen
        $stmt = $db->prepare("DELETE FROM answers WHERE question_id = ?");
        $stmt->execute([$questionId]);
        
        $stmt = $db->prepare("INSERT INTO answers (question_id, answer_text, is_correct) VALUES (?, ?, ?)");
        foreach ($answers as $answer) {
            $stmt->execute([$questionId, $answer['text'], $answer['isCorrect']]);
        }
        
        $db->commit();
        return true;
    } catch (Exception $e) {
        $db->rollBack();
        error_log("Fehler beim Aktualisieren der Frage: " . $e->getMessage());
        return false;
    }
}

// Funktion zum Löschen einer Frage
function deleteQuestion($db, $questionId) {
    $db->beginTransaction();
    
    try {
        // Zugehörige Einträge in question_categories löschen
        $stmt = $db->prepare("DELETE FROM question_categories WHERE question_id = ?");
        $stmt->execute([$questionId]);
        
        // Zugehörige Antworten löschen
        $stmt = $db->prepare("DELETE FROM answers WHERE question_id = ?");
        $stmt->execute([$questionId]);
        
        // Frage löschen
        $stmt = $db->prepare("DELETE FROM questions WHERE id = ?");
        $stmt->execute([$questionId]);
        
        $db->commit();
        return true;
    } catch (Exception $e) {
        $db->rollBack();
        error_log("Fehler beim Löschen der Frage: " . $e->getMessage());
        return false;
    }
}
