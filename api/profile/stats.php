<?php
// api/profile/stats.php
require_once __DIR__ . '/../config/database.php';

$db = Database::getInstance();
$conn = $db->getConnection();
$user_id = $db->requireAuth();

// Количество мероприятий
$stmt = $conn->prepare("SELECT COUNT(*) as count FROM events WHERE user_id = ?");
$stmt->execute([$user_id]);
$events = $stmt->fetch(PDO::FETCH_ASSOC);

// Количество задач
$stmt = $conn->prepare("
    SELECT COUNT(*) as count FROM tasks t
    JOIN events e ON t.event_id = e.event_id
    WHERE e.user_id = ?
");
$stmt->execute([$user_id]);
$tasks = $stmt->fetch(PDO::FETCH_ASSOC);

// Ближайшее мероприятие
$stmt = $conn->prepare("
    SELECT * FROM events 
    WHERE user_id = ? AND event_date > datetime('now') 
    ORDER BY event_date ASC LIMIT 1
");
$stmt->execute([$user_id]);
$nextEvent = $stmt->fetch(PDO::FETCH_ASSOC);

// Дата регистрации
$stmt = $conn->prepare("SELECT created_at FROM users WHERE user_id = ?");
$stmt->execute([$user_id]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

$db->sendSuccess([
    'events_count' => $events['count'],
    'tasks_count' => $tasks['count'],
    'next_event' => $nextEvent,
    'registered_at' => $user['created_at']
]);
?>