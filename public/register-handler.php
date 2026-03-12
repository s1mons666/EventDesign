<?php
// public/register-handler.php
require_once __DIR__ . '/../api/config/database.php';
require_once __DIR__ . '/../api/helpers/uuid.php';

header('Content-Type: application/json');

try {
    // Получаем данные из POST
    $first_name = $_POST['first_name'] ?? '';
    $last_name = $_POST['last_name'] ?? '';
    $email = $_POST['email'] ?? '';
    $password = $_POST['password'] ?? '';
    
    // Логируем для отладки
    error_log("Register attempt: " . print_r($_POST, true));
    
    // Валидация
    if (empty($first_name)) throw new Exception('Имя обязательно');
    if (empty($last_name)) throw new Exception('Фамилия обязательна');
    if (empty($email)) throw new Exception('Email обязателен');
    if (empty($password)) throw new Exception('Пароль обязателен');
    if (strlen($password) < 6) throw new Exception('Пароль должен быть минимум 6 символов');
    
    $db = Database::getInstance();
    $conn = $db->getConnection();
    
    // Проверяем существование пользователя
    $stmt = $conn->prepare("SELECT user_id FROM users WHERE email = ?");
    $stmt->execute([$email]);
    
    if ($stmt->fetch()) {
        throw new Exception('Email уже зарегистрирован');
    }
    
    // Создаем пользователя
    $user_id = generateUUID();
    $password_hash = password_hash($password, PASSWORD_DEFAULT);
    
    $stmt = $conn->prepare("
        INSERT INTO users (user_id, email, password_hash, first_name, last_name) 
        VALUES (?, ?, ?, ?, ?)
    ");
    
    $result = $stmt->execute([$user_id, $email, $password_hash, $first_name, $last_name]);
    
    if (!$result) {
        throw new Exception('Ошибка при создании пользователя');
    }
    
    // Создаем сессию
    $token = bin2hex(random_bytes(32));
    $session_id = generateUUID();
    $expires_at = date('Y-m-d H:i:s', strtotime('+30 days'));
    
    $stmt = $conn->prepare("
        INSERT INTO sessions (session_id, user_id, token, expires_at) 
        VALUES (?, ?, ?, ?)
    ");
    $stmt->execute([$session_id, $user_id, $token, $expires_at]);
    
    // Устанавливаем куку
    setcookie('auth_token', $token, time() + 30*24*60*60, '/');
    
    // Запускаем сессию
    session_start();
    $_SESSION['user_id'] = $user_id;
    
    echo json_encode([
        'success' => true,
        'message' => 'Регистрация успешна',
        'data' => [
            'token' => $token,
            'user' => [
                'user_id' => $user_id,
                'email' => $email,
                'first_name' => $first_name,
                'last_name' => $last_name
            ]
        ]
    ]);
    
} catch (Exception $e) {
    error_log("Register error: " . $e->getMessage());
    echo json_encode([
        'success' => false, 
        'message' => $e->getMessage()
    ]);
}
?>