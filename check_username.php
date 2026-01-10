<?php
require_once 'config.php';

header('Content-Type: application/json');

if (isset($_POST['username'])) {
    $username = trim($_POST['username']);
    $user_id = isset($_POST['user_id']) ? intval($_POST['user_id']) : 0;
    
    // If user_id is provided, exclude that user from the check (for editing)
    if ($user_id > 0) {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE username = ? AND id != ?");
        $stmt->execute([$username, $user_id]);
    } else {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE username = ?");
        $stmt->execute([$username]);
    }
    
    $count = $stmt->fetchColumn();
    
    echo json_encode(['exists' => $count > 0]);
}
?>
