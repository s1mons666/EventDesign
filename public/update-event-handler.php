<?php
// public/update-event-handler.php
session_start();
require_once __DIR__ . '/../api/config/database.php';

header('Content-Type: application/json');

// Проверка авторизации
$user_id = null;

if (isset($_SESSION['user_id'])) {
    $user_id = $_SESSION['user_id'];
} elseif (isset($_COOKIE['auth_token'])) {
    $token = $_COOKIE['auth_token'];
    $db = Database::getInstance();
    $conn = $db->getConnection();
    $stmt = $conn->prepare("SELECT user_id FROM sessions WHERE token = ? AND expires_at > datetime('now')");
    $stmt->execute([$token]);
    $session = $stmt->fetch();
    if ($session) {
        $user_id = $session['user_id'];
        $_SESSION['user_id'] = $user_id;
    }
}

if (!$user_id) {
    echo json_encode(['success' => false, 'message' => 'Требуется авторизация']);
    exit;
}

try {
    // Получаем данные из POST
    $event_id = $_POST['id'] ?? '';
    $title = $_POST['title'] ?? '';
    $event_date = $_POST['event_date'] ?? '';
    $location = $_POST['location'] ?? '';
    $budget = $_POST['budget'] ?? '0';
    $status = $_POST['status'] ?? 'draft';
    $description = $_POST['description'] ?? '';
    
    // Логируем для отладки
    error_log("Updating event ID: $event_id for user: $user_id");
    
    // ВАЛИДАЦИЯ (новая)
    if (empty($event_id)) {
        echo json_encode(['success' => false, 'message' => 'ID мероприятия не указан']);
        exit;
    }
    
    if (empty($title)) {
        echo json_encode(['success' => false, 'message' => 'Название мероприятия обязательно']);
        exit;
    }
    
    if (strlen($title) < 3) {
        echo json_encode(['success' => false, 'message' => 'Название должно быть минимум 3 символа']);
        exit;
    }
    
    if (strlen($title) > 100) {
        echo json_encode(['success' => false, 'message' => 'Название не может быть длиннее 100 символов']);
        exit;
    }
    
    if (empty($event_date)) {
        echo json_encode(['success' => false, 'message' => 'Дата мероприятия обязательна']);
        exit;
    }
    
    // Проверка даты
    $event_timestamp = strtotime($event_date);
    $min_date = strtotime('-1 year'); // Можно создать мероприятия за прошлый год
    $max_date = strtotime('+5 years'); // Не дальше 5 лет

    if ($event_timestamp < $min_date) {
        echo json_encode(['success' => false, 'message' => 'Дата не может быть более года назад']);
        exit;
    }

    if ($event_timestamp > $max_date) {
        echo json_encode(['success' => false, 'message' => 'Дата не может быть более чем через 5 лет']);
        exit;
    }

    // Валидация бюджета
    if (!empty($budget) && (!is_numeric($budget) || $budget < 0)) {
        echo json_encode(['success' => false, 'message' => 'Бюджет должен быть положительным числом']);
        exit;
    }

    if (!empty($budget) && $budget > 999999999) {
        echo json_encode(['success' => false, 'message' => 'Бюджет слишком большой']);
        exit;
    }
    
    $db = Database::getInstance();
    $conn = $db->getConnection();
    
    // Проверяем, принадлежит ли мероприятие пользователю
    $stmt = $conn->prepare("SELECT * FROM events WHERE event_id = ? AND user_id = ?");
    $stmt->execute([$event_id, $user_id]);
    $event = $stmt->fetch();
    
    if (!$event) {
        echo json_encode(['success' => false, 'message' => 'Мероприятие не найдено']);
        exit;
    }
    
    // Обработка нового фото
    $photo_path = $event['photo_path'];
    $photo_name = $event['photo_name'];
    
    // Проверяем, нужно ли удалить фото
    $delete_photo = isset($_POST['delete_photo']) && $_POST['delete_photo'] == '1';
    
    if ($delete_photo && $photo_path && file_exists(__DIR__ . '/' . $photo_path)) {
        unlink(__DIR__ . '/' . $photo_path);
        $photo_path = null;
        $photo_name = null;
    }
    
    // Обработка загрузки нового фото
    if (isset($_FILES['photo']) && $_FILES['photo']['error'] === UPLOAD_ERR_OK) {
        // Удаляем старое фото если есть
        if ($photo_path && file_exists(__DIR__ . '/' . $photo_path)) {
            unlink(__DIR__ . '/' . $photo_path);
        }
        
        $upload_dir = __DIR__ . '/uploads/events/';
        
        if (!file_exists($upload_dir)) {
            mkdir($upload_dir, 0777, true);
        }
        
        $file = $_FILES['photo'];
        $file_extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        $allowed_extensions = ['jpg', 'jpeg', 'png', 'gif'];
        
        if (in_array($file_extension, $allowed_extensions) && $file['size'] <= 5 * 1024 * 1024) {
            $new_filename = $event_id . '_' . time() . '.' . $file_extension;
            $upload_path = $upload_dir . $new_filename;
            
            if (move_uploaded_file($file['tmp_name'], $upload_path)) {
                $photo_path = 'uploads/events/' . $new_filename;
                $photo_name = $file['name'];
            }
        }
    }
    
    // Обновляем мероприятие
    $stmt = $conn->prepare("
        UPDATE events 
        SET title = ?, event_date = ?, location = ?, budget = ?, status = ?, description = ?, photo_path = ?, photo_name = ?
        WHERE event_id = ? AND user_id = ?
    ");
    
    $result = $stmt->execute([
        $title,
        $event_date,
        $location ?: null,
        $budget ? floatval($budget) : null,
        $status,
        $description ?: null,
        $photo_path,
        $photo_name,
        $event_id,
        $user_id
    ]);
    
    if ($result) {
        echo json_encode(['success' => true, 'message' => 'Мероприятие обновлено']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Ошибка при обновлении']);
    }
    
} catch (PDOException $e) {
    error_log("Database error in update: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Ошибка базы данных: ' . $e->getMessage()]);
} catch (Exception $e) {
    error_log("General error in update: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Ошибка: ' . $e->getMessage()]);
}
?>