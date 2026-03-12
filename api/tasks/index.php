<?php
// api/tasks/index.php
header('Content-Type: application/json');

try {
    require_once __DIR__ . '/../config/database.php';
    require_once __DIR__ . '/../helpers/uuid.php';
    require_once __DIR__ . '/../auth/check.php';
    
    $db = Database::getInstance();
    $conn = $db->getConnection();
    
    $method = $_SERVER['REQUEST_METHOD'];
    
    // Получение списка задач
    if ($method === 'GET') {
        if (!isset($_GET['event_id'])) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'ID мероприятия не указан']);
            exit;
        }
        
        $stmt = $conn->prepare("SELECT * FROM tasks WHERE event_id = ? ORDER BY created_at");
        $stmt->execute([$_GET['event_id']]);
        $tasks = $stmt->fetchAll();
        
        echo json_encode(['success' => true, 'data' => $tasks]);
    }
    
    // Создание задачи
    elseif ($method === 'POST') {
        $data = json_decode(file_get_contents('php://input'), true);
        
        if (empty($data['title'])) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Название задачи обязательно']);
            exit;
        }
        
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
    }
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>