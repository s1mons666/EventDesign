<?php
// public/update-event-handler.php
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
    // Получаем данные из POST
    $event_id = $_POST['id'] ?? '';
    $title = $_POST['title'] ?? '';
    $event_date = $_POST['event_date'] ?? '';
    $location = $_POST['location'] ?? '';
    $budget = $_POST['budget'] ?? '0';
    $status = $_POST['status'] ?? 'draft';
    $description = $_POST['description'] ?? '';
    
    // Логируем для отладки
    error_log("Updating event ID: $event_id for user: $user_id");
    error_log("Update data: " . print_r($_POST, true));
    
    // Валидация
    if (empty($event_id)) {
        echo json_encode(['success' => false, 'message' => 'ID мероприятия не указан']);
        exit;
    }
    
    if (empty($title)) {
        echo json_encode(['success' => false, 'message' => 'Название мероприятия обязательно']);
        exit;
    }
    
    if (empty($event_date)) {
        echo json_encode(['success' => false, 'message' => 'Дата мероприятия обязательна']);
        exit;
    }
    
    $db = Database::getInstance();
    $conn = $db->getConnection();
    
    // Проверяем, принадлежит ли мероприятие пользователю
    $stmt = $conn->prepare("SELECT event_id FROM events WHERE event_id = ? AND user_id = ?");
    $stmt->execute([$event_id, $user_id]);
    
    if (!$stmt->fetch()) {
        echo json_encode(['success' => false, 'message' => 'Мероприятие не найдено или доступ запрещен']);
        exit;
    }
    
    // Обновляем мероприятие
    $stmt = $conn->prepare("
        UPDATE events 
        SET title = ?, event_date = ?, location = ?, budget = ?, status = ?, description = ?
        WHERE event_id = ? AND user_id = ?
    ");
    
    $result = $stmt->execute([
        $title,
        $event_date,
        $location ?: null,
        $budget ? floatval($budget) : null,
        $status,
        $description ?: null,
        $event_id,
        $user_id
    ]);
    
    if ($result) {
        echo json_encode([
            'success' => true,
            'message' => 'Мероприятие успешно обновлено'
        ]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Ошибка при обновлении мероприятия']);
    }
    
} catch (PDOException $e) {
    error_log("Database error in update: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Ошибка базы данных: ' . $e->getMessage()]);
} catch (Exception $e) {
    error_log("General error in update: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Ошибка: ' . $e->getMessage()]);
}
?>