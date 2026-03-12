<?php
// api/tasks/delete.php
header('Content-Type: application/json');

try {
    require_once __DIR__ . '/../config/database.php';
    require_once __DIR__ . '/../auth/check.php';
    
    if (!isset($_GET['id'])) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'ID задачи не указан']);
        exit;
    }
    
    $db = Database::getInstance();
    $conn = $db->getConnection();
    
    $stmt = $conn->prepare("DELETE FROM tasks WHERE task_id = ?");
    $stmt->execute([$_GET['id']]);
    
    echo json_encode(['success' => true, 'message' => 'Задача удалена']);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>