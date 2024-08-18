<?php
function updateUserStatistics($conn, $user_id, $question_id, $is_correct) {
    $sql = "INSERT INTO user_statistics (user_id, question_id, correct_count, incorrect_count) 
            VALUES (?, ?, ?, ?) 
            ON DUPLICATE KEY UPDATE 
            correct_count = correct_count + ?, 
            incorrect_count = incorrect_count + ?";
    
    $correct_increment = $is_correct ? 1 : 0;
    $incorrect_increment = $is_correct ? 0 : 1;
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("iiiiii", $user_id, $question_id, $correct_increment, $incorrect_increment, $correct_increment, $incorrect_increment);
    $stmt->execute();
}
?>