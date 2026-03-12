<?php
// api/reports/generate.php
require_once __DIR__ . '/../config/database.php';

$db = Database::getInstance();
$conn = $db->getConnection();
$user_id = $db->requireAuth();
$input = $db->getInput();

$event_id = $input['event_id'] ?? null;
if (!$event_id) $db->sendError('ID мероприятия не указан', 400);

// Получаем мероприятие
$stmt = $conn->prepare("SELECT * FROM events WHERE event_id = ? AND user_id = ?");
$stmt->execute([$event_id, $user_id]);
$event = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$event) $db->sendError('Мероприятие не найдено', 404);

// Получаем задачи
$stmt = $conn->prepare("SELECT * FROM tasks WHERE event_id = ? ORDER BY created_at");
$stmt->execute([$event_id]);
$tasks = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Вычисляем статистику
$total_cost = array_sum(array_column($tasks, 'cost'));
$completed = count(array_filter($tasks, fn($t) => $t['is_done'] == 1));
$pending = count($tasks) - $completed;

// Сохраняем отчет
$report_id = $db->generateUUID();
$report_data = json_encode([
    'event' => $event,
    'tasks' => $tasks,
    'total_cost' => $total_cost,
    'completed' => $completed,
    'pending' => $pending,
    'generated_at' => date('Y-m-d H:i:s')
]);

$stmt = $conn->prepare("
    INSERT INTO reports (report_id, event_id, user_id, report_data, total_amount, tasks_count, completed_count) 
    VALUES (?, ?, ?, ?, ?, ?, ?)
");
$stmt->execute([
    $report_id, 
    $event_id, 
    $user_id, 
    $report_data, 
    $total_cost, 
    count($tasks), 
    $completed
]);

$db->sendSuccess([
    'event' => $event,
    'tasks' => $tasks,
    'total_cost' => $total_cost,
    'completed' => $completed,
    'pending' => $pending
], 'Отчет сгенерирован');
?>