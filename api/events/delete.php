<?php
// api/events/delete.php
header('Content-Type: application/json');

try {
    require_once __DIR__ . '/../config/database.php';
    require_once __DIR__ . '/../auth/check.php';
    
    if (!isset($_GET['id'])) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'ID мероприятия не указан']);
        exit;
    }
    
    $db = Database::getInstance();
    $conn = $db->getConnection();
    
    $stmt = $conn->prepare("DELETE FROM events WHERE event_id = ? AND user_id = ?");
    $stmt->execute([$_GET['id'], $_GET['user_id']]);
    
    echo json_encode(['success' => true, 'message' => 'Мероприятие удалено']);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>