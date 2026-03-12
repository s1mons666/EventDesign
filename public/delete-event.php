<?php
// public/delete-event.php
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

// Получаем ID мероприятия из POST
$event_id = $_POST['id'] ?? '';

if (empty($event_id)) {
    echo json_encode(['success' => false, 'message' => 'ID мероприятия не указан']);
    exit;
}

try {
    $db = Database::getInstance();
    $conn = $db->getConnection();
    
    // Начинаем транзакцию
    $conn->beginTransaction();
    
    // Проверяем, принадлежит ли мероприятие пользователю
    $stmt = $conn->prepare("SELECT event_id FROM events WHERE event_id = ? AND user_id = ?");
    $stmt->execute([$event_id, $user_id]);
    
    if (!$stmt->fetch()) {
        $conn->rollBack();
        echo json_encode(['success' => false, 'message' => 'Мероприятие не найдено или доступ запрещен']);
        exit;
    }
    
    // Сначала удаляем все задачи мероприятия
    $stmt = $conn->prepare("DELETE FROM tasks WHERE event_id = ?");
    $stmt->execute([$event_id]);
    $tasks_deleted = $stmt->rowCount();
    
    // Затем удаляем само мероприятие
    $stmt = $conn->prepare("DELETE FROM events WHERE event_id = ? AND user_id = ?");
    $stmt->execute([$event_id, $user_id]);
    $event_deleted = $stmt->rowCount();
    
    // Подтверждаем транзакцию
    $conn->commit();
    
    echo json_encode([
        'success' => true, 
        'message' => "Мероприятие удалено. Удалено задач: $tasks_deleted"
    ]);
    
} catch (PDOException $e) {
    // Откатываем транзакцию в случае ошибки
    if ($conn->inTransaction()) {
        $conn->rollBack();
    }
    error_log("Delete error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Ошибка базы данных: ' . $e->getMessage()]);
} catch (Exception $e) {
    if ($conn->inTransaction()) {
        $conn->rollBack();
    }
    error_log("Delete error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Ошибка: ' . $e->getMessage()]);
}
?>