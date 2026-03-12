<?php
// api/tasks/create.php
header('Content-Type: application/json');

try {
    require_once __DIR__ . '/../config/database.php';
    require_once __DIR__ . '/../helpers/uuid.php';
    
    $data = json_decode(file_get_contents('php://input'), true);
    
    if (!$data) {
        throw new Exception('Invalid request data');
    }
    
    if (empty($data['title'])) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Название задачи обязательно']);
        exit;
    }
    
    if (empty($data['event_id'])) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'ID мероприятия не указан']);
        exit;
    }
    
    $db = Database::getInstance();
    $conn = $db->getConnection();
    
    $task_id = generateUUID();
    
    $stmt = $conn->prepare("
        INSERT INTO tasks (task_id, event_id, title, cost, is_completed) 
        VALUES (?, ?, ?, ?, ?)
    ");
    
    $stmt->execute([
        $task_id,
        $data['event_id'],
        $data['title'],
        $data['cost'] ?? 0,
        $data['is_completed'] ?? 0
    ]);
    
    echo json_encode([
        'success' => true,
        'message' => 'Задача создана',
        'data' => ['task_id' => $task_id]
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>