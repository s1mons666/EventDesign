<?php
// public/create-task-handler.php
session_start();
require_once __DIR__ . '/../api/config/database.php';
require_once __DIR__ . '/../api/helpers/uuid.php';

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
    $event_id = $_POST['event_id'] ?? '';
    $title = $_POST['title'] ?? '';
    $cost = $_POST['cost'] ?? '0';
    $is_completed = $_POST['is_completed'] ?? '0';
    
    if (empty($title)) {
        echo json_encode(['success' => false, 'message' => 'Название задачи обязательно']);
        exit;
    }
    
    if (empty($event_id)) {
        echo json_encode(['success' => false, 'message' => 'ID мероприятия не указан']);
        exit;
    }
    
    $db = Database::getInstance();
    $conn = $db->getConnection();
    
    // Проверяем, принадлежит ли мероприятие пользователю
    $stmt = $conn->prepare("SELECT event_id FROM events WHERE event_id = ? AND user_id = ?");
    $stmt->execute([$event_id, $user_id]);
    
    if (!$stmt->fetch()) {
        echo json_encode(['success' => false, 'message' => 'Мероприятие не найдено']);
        exit;
    }
    
    $task_id = generateUUID();
    
    $stmt = $conn->prepare("
        INSERT INTO tasks (task_id, event_id, title, cost, is_completed) 
        VALUES (?, ?, ?, ?, ?)
    ");
    
    $stmt->execute([
        $task_id,
        $event_id,
        $title,
        floatval($cost),
        intval($is_completed)
    ]);
    
    echo json_encode(['success' => true, 'message' => 'Задача создана']);
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>