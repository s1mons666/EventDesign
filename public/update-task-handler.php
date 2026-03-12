<?php
// public/update-task-handler.php
session_start();
require_once __DIR__ . '/../api/config/database.php';

header('Content-Type: application/json');

// Проверка авторизации
$user_id = null;

if (isset($_SESSION['user_id'])) {
    $user_id = $_SESSION['user_id'];
} elseif (isset($_COOKIE['auth_token'])) {
    $token = $_COOKIE['auth_token'];
    $db = Database::getInstance();
    $conn = $db->getConnection();
    $stmt = $conn->prepare("SELECT user_id FROM sessions WHERE token = ? AND expires_at > datetime('now')");
    $stmt->execute([$token]);
    $session = $stmt->fetch();
    if ($session) {
        $user_id = $session['user_id'];
        $_SESSION['user_id'] = $user_id;
    }
}

if (!$user_id) {
    echo json_encode(['success' => false, 'message' => 'Требуется авторизация']);
    exit;
}

try {
    $task_id = $_POST['id'] ?? '';
    $is_completed = $_POST['is_completed'] ?? null;
    
    if (empty($task_id)) {
        echo json_encode(['success' => false, 'message' => 'ID задачи не указан']);
        exit;
    }
    
    $db = Database::getInstance();
    $conn = $db->getConnection();
    
    if ($is_completed !== null) {
        $stmt = $conn->prepare("UPDATE tasks SET is_completed = ? WHERE task_id = ?");
        $stmt->execute([intval($is_completed), $task_id]);
    }
    
    echo json_encode(['success' => true, 'message' => 'Задача обновлена']);
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>