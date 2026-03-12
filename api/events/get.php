<?php
// api/events/get.php
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
    
    $stmt = $conn->prepare("SELECT * FROM events WHERE event_id = ?");
    $stmt->execute([$_GET['id']]);
    $event = $stmt->fetch();
    
    if (!$event) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Мероприятие не найдено']);
        exit;
    }
    
    echo json_encode(['success' => true, 'data' => $event]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>