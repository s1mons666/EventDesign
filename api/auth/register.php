<?php
// api/auth/register.php
header('Content-Type: application/json');

try {
    require_once __DIR__ . '/../config/database.php';
    require_once __DIR__ . '/../helpers/uuid.php';
    
    $data = json_decode(file_get_contents('php://input'), true);
    
    if (!$data) {
        throw new Exception('Invalid request data');
    }
    
    error_log("Register data: " . print_r($data, true));
    
    // Валидация
    $errors = [];
    if (empty($data['first_name'])) $errors['first_name'] = 'Имя обязательно';
    if (empty($data['last_name'])) $errors['last_name'] = 'Фамилия обязательна';
    if (empty($data['email'])) $errors['email'] = 'Email обязателен';
    if (empty($data['password'])) $errors['password'] = 'Пароль обязателен';
    if (strlen($data['password']) < 6) $errors['password'] = 'Пароль должен быть минимум 6 символов';
    
    if (!empty($errors)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'errors' => $errors]);
        exit;
    }
    
    $db = Database::getInstance();
    $conn = $db->getConnection();
    
    // Проверка существования пользователя
    $stmt = $conn->prepare("SELECT user_id FROM users WHERE email = ?");
    $stmt->execute([$data['email']]);
    
    if ($stmt->fetch()) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Email уже зарегистрирован']);
        exit;
    }
    
    // Создание пользователя
    $user_id = generateUUID();
    $password_hash = password_hash($data['password'], PASSWORD_DEFAULT);
    
    $stmt = $conn->prepare("
        INSERT INTO users (user_id, email, password_hash, first_name, last_name) 
        VALUES (?, ?, ?, ?, ?)
    ");
    
    $stmt->execute([
        $user_id,
        $data['email'],
        $password_hash,
        $data['first_name'],
        $data['last_name']
    ]);
    
    // Создание сессии
    $token = bin2hex(random_bytes(32));
    $session_id = generateUUID();
    $expires_at = date('Y-m-d H:i:s', strtotime('+30 days'));
    
    $stmt = $conn->prepare("
        INSERT INTO sessions (session_id, user_id, token, expires_at) 
        VALUES (?, ?, ?, ?)
    ");
    $stmt->execute([$session_id, $user_id, $token, $expires_at]);
    
    // Установка куки
    setcookie('auth_token', $token, time() + 30*24*60*60, '/');
    
    // Получение данных пользователя
    $stmt = $conn->prepare("SELECT user_id, email, first_name, last_name FROM users WHERE user_id = ?");
    $stmt->execute([$user_id]);
    $user = $stmt->fetch();
    
    echo json_encode([
        'success' => true,
        'message' => 'Регистрация успешна',
        'data' => [
            'token' => $token,
            'user' => $user
        ]
    ]);
    
} catch (Exception $e) {
    error_log("Register error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Ошибка сервера: ' . $e->getMessage()]);
}
?>