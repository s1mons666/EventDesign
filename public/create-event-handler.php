<?php
// public/create-event-handler.php
session_start();
require_once __DIR__ . '/../api/config/database.php';
require_once __DIR__ . '/../api/helpers/uuid.php';

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
    $title = $_POST['title'] ?? '';
    $event_date = $_POST['event_date'] ?? '';
    $location = $_POST['location'] ?? '';
    $budget = $_POST['budget'] ?? '0';
    $status = $_POST['status'] ?? 'draft';
    $description = $_POST['description'] ?? '';
    
    // Логируем для отладки
    error_log("Creating event for user: $user_id");
    error_log("Event date: $event_date");
    
    // Валидация
    if (empty($title)) {
        echo json_encode(['success' => false, 'message' => 'Название мероприятия обязательно']);
        exit;
    }
    
    if (empty($event_date)) {
        echo json_encode(['success' => false, 'message' => 'Дата мероприятия обязательна']);
        exit;
    }
    
    // ПРОВЕРКА ЧТО ДАТА НЕ В ПРОШЛОМ (СЕРВЕРНАЯ)
    $event_timestamp = strtotime($event_date);
    $today_timestamp = strtotime('today');
    
    if ($event_timestamp < $today_timestamp) {
        echo json_encode(['success' => false, 'message' => 'Дата мероприятия не может быть в прошлом']);
        exit;
    }
    
    // Проверка что дата не слишком далеко в будущем (опционально)
    $max_date_timestamp = strtotime('+5 years');
    if ($event_timestamp > $max_date_timestamp) {
        echo json_encode(['success' => false, 'message' => 'Дата не может быть более чем через 5 лет']);
        exit;
    }
    
    $db = Database::getInstance();
    $conn = $db->getConnection();
    
    // Генерируем UUID для мероприятия
    $event_id = generateUUID();
    
    // Обработка загруженного фото
    $photo_path = null;
    $photo_name = null;
    
    if (isset($_FILES['photo']) && $_FILES['photo']['error'] === UPLOAD_ERR_OK) {
        $upload_dir = __DIR__ . '/uploads/events/';
        
        // Создаем папку если её нет
        if (!file_exists($upload_dir)) {
            mkdir($upload_dir, 0777, true);
            error_log("Created upload directory: $upload_dir");
        }
        
        $file = $_FILES['photo'];
        $file_extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        $allowed_extensions = ['jpg', 'jpeg', 'png', 'gif'];
        
        // Проверка расширения
        if (!in_array($file_extension, $allowed_extensions)) {
            echo json_encode(['success' => false, 'message' => 'Неподдерживаемый формат файла. Используйте JPG, PNG или GIF']);
            exit;
        }
        
        // Проверка размера (5 МБ)
        if ($file['size'] > 5 * 1024 * 1024) {
            echo json_encode(['success' => false, 'message' => 'Файл слишком большой. Максимальный размер 5 МБ']);
            exit;
        }
        
        // Генерируем уникальное имя файла
        $new_filename = $event_id . '_' . time() . '.' . $file_extension;
        $upload_path = $upload_dir . $new_filename;
        
        // Перемещаем файл
        if (move_uploaded_file($file['tmp_name'], $upload_path)) {
            $photo_path = 'uploads/events/' . $new_filename;
            $photo_name = $file['name'];
            error_log("Photo uploaded successfully: $photo_path");
        } else {
            error_log("Failed to upload photo. Error: " . $_FILES['photo']['error']);
        }
    }
    
    // Проверяем структуру таблицы
    $columns = $conn->query("PRAGMA table_info(events)");
    $column_names = [];
    while ($col = $columns->fetch()) {
        $column_names[] = $col['name'];
    }
    
    // Динамически формируем запрос в зависимости от наличия колонок
    $has_photo_path = in_array('photo_path', $column_names);
    $has_photo_name = in_array('photo_name', $column_names);
    
    if ($has_photo_path && $has_photo_name) {
        $sql = "INSERT INTO events (event_id, user_id, title, event_date, location, budget, status, description, photo_path, photo_name) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
        $params = [
            $event_id,
            $user_id,
            $title,
            $event_date,
            $location ?: null,
            $budget ? floatval($budget) : null,
            $status,
            $description ?: null,
            $photo_path,
            $photo_name
        ];
    } else {
        // Если колонок для фото нет, вставляем без них
        $sql = "INSERT INTO events (event_id, user_id, title, event_date, location, budget, status, description) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
        $params = [
            $event_id,
            $user_id,
            $title,
            $event_date,
            $location ?: null,
            $budget ? floatval($budget) : null,
            $status,
            $description ?: null
        ];
    }
    
    $stmt = $conn->prepare($sql);
    $result = $stmt->execute($params);
    
    if ($result) {
        echo json_encode([
            'success' => true,
            'message' => 'Мероприятие успешно создано',
            'event_id' => $event_id,
            'photo' => $photo_path
        ]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Ошибка при создании мероприятия']);
    }
    
} catch (PDOException $e) {
    error_log("Database error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Ошибка базы данных: ' . $e->getMessage()]);
} catch (Exception $e) {
    error_log("General error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Ошибка: ' . $e->getMessage()]);
}
?>