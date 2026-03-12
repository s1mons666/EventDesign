<?php
// api/events/index.php
header('Content-Type: application/json');

try {
    require_once __DIR__ . '/../config/database.php';
    require_once __DIR__ . '/../helpers/uuid.php';
    require_once __DIR__ . '/../auth/check.php';
    
    $db = Database::getInstance();
    $conn = $db->getConnection();
    
    $method = $_SERVER['REQUEST_METHOD'];
    
    // Получение списка мероприятий
    if ($method === 'GET') {
        $user_id = $_GET['user_id'] ?? null;
        $filter = $_GET['filter'] ?? 'all';
        $sort = $_GET['sort'] ?? 'date_desc';
        
        $query = "SELECT * FROM events WHERE user_id = :user_id";
        $params = [':user_id' => $user_id];
        
        if ($filter === 'upcoming') {
            $query .= " AND event_date > datetime('now')";
        } elseif ($filter === 'past') {
            $query .= " AND event_date < datetime('now')";
        }
        
        if ($sort === 'date_desc') {
            $query .= " ORDER BY event_date DESC";
        } elseif ($sort === 'date_asc') {
            $query .= " ORDER BY event_date ASC";
        } elseif ($sort === 'name') {
            $query .= " ORDER BY title ASC";
        }
        
        $stmt = $conn->prepare($query);
        $stmt->execute($params);
        $events = $stmt->fetchAll();
        
        // Получаем статистику по задачам для каждого мероприятия
        foreach ($events as &$event) {
            $stmt = $conn->prepare("
                SELECT 
                    COUNT(*) as total_tasks,
                    SUM(CASE WHEN is_completed = 1 THEN 1 ELSE 0 END) as completed_tasks,
                    SUM(cost) as total_cost
                FROM tasks 
                WHERE event_id = ?
            ");
            $stmt->execute([$event['event_id']]);
            $event['stats'] = $stmt->fetch();
        }
        
        echo json_encode(['success' => true, 'data' => $events]);
    }
    
    // Создание мероприятия
    elseif ($method === 'POST') {
        $data = json_decode(file_get_contents('php://input'), true);
        
        if (empty($data['title']) || empty($data['event_date'])) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Название и дата обязательны']);
            exit;
        }
        
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
    }
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>