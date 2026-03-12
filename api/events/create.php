<?php
// api/events/create.php
header('Content-Type: application/json');

try {
    require_once __DIR__ . '/../config/database.php';
    require_once __DIR__ . '/../helpers/uuid.php';
    
    $data = json_decode(file_get_contents('php://input'), true);
    
    if (!$data) {
        throw new Exception('Invalid request data');
    }
    
    if (empty($data['title']) || empty($data['event_date'])) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Название и дата обязательны']);
        exit;
    }
    
    $db = Database::getInstance();
    $conn = $db->getConnection();
    
    $event_id = generateUUID();
    
    $stmt = $conn->prepare("
        INSERT INTO events (event_id, user_id, title, event_date, location, budget, status, description) 
        VALUES (?, ?, ?, ?, ?, ?, ?, ?)
    ");
    
    $stmt->execute([
        $event_id,
        $data['user_id'],
        $data['title'],
        $data['event_date'],
        $data['location'] ?? null,
        $data['budget'] ?? null,
        $data['status'] ?? 'draft',
        $data['description'] ?? null
    ]);
    
    echo json_encode([
        'success' => true,
        'message' => 'Мероприятие создано',
        'data' => ['event_id' => $event_id]
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>