<?php
// api/tasks/update.php
header('Content-Type: application/json');

try {
    require_once __DIR__ . '/../config/database.php';
    require_once __DIR__ . '/../auth/check.php';
    
    $data = json_decode(file_get_contents('php://input'), true);
    
    if (empty($data['id'])) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'ID задачи не указан']);
        exit;
    }
    
    $db = Database::getInstance();
    $conn = $db->getConnection();
    
    $updates = [];
    $params = [];
    
    if (isset($data['title'])) {
        $updates[] = "title = ?";
        $params[] = $data['title'];
    }
    
    if (isset($data['cost'])) {
        $updates[] = "cost = ?";
        $params[] = $data['cost'];
    }
    
    if (isset($data['is_completed'])) {
        $updates[] = "is_completed = ?";
        $params[] = $data['is_completed'];
    }
    
    if (empty($updates)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Нет данных для обновления']);
        exit;
    }
    
    $params[] = $data['id'];
    
    $stmt = $conn->prepare("UPDATE tasks SET " . implode(', ', $updates) . " WHERE task_id = ?");
    $stmt->execute($params);
    
    echo json_encode(['success' => true, 'message' => 'Задача обновлена']);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>