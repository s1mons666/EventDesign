<?php
// api/events/update.php
header('Content-Type: application/json');

try {
    require_once __DIR__ . '/../config/database.php';
    require_once __DIR__ . '/../auth/check.php';
    
    $data = json_decode(file_get_contents('php://input'), true);
    
    if (empty($data['id'])) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'ID мероприятия не указан']);
        exit;
    }
    
    $db = Database::getInstance();
    $conn = $db->getConnection();
    
    $stmt = $conn->prepare("
        UPDATE events 
        SET title = ?, event_date = ?, location = ?, budget = ?, status = ?, description = ?
        WHERE event_id = ? AND user_id = ?
    ");
    
    $stmt->execute([
        $data['title'],
        $data['event_date'],
        $data['location'] ?? null,
        $data['budget'] ?? null,
        $data['status'] ?? 'draft',
        $data['description'] ?? null,
        $data['id'],
        $data['user_id']
    ]);
    
    echo json_encode(['success' => true, 'message' => 'Мероприятие обновлено']);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>