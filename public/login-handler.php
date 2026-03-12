<?php
// public/login-handler.php
require_once __DIR__ . '/../api/config/database.php';
require_once __DIR__ . '/../api/helpers/uuid.php';

header('Content-Type: application/json');

try {
    // Получаем данные из POST (не из json)
    $email = $_POST['email'] ?? '';
    $password = $_POST['password'] ?? '';
    $rememberMe = isset($_POST['rememberMe']) ? $_POST['rememberMe'] === 'true' : false;
    
    if (empty($email) || empty($password)) {
        echo json_encode(['success' => false, 'message' => 'Email и пароль обязательны']);
        exit;
    }
    
    $db = Database::getInstance();
    $conn = $db->getConnection();
    
    // Ищем пользователя
    $stmt = $conn->prepare("SELECT * FROM users WHERE email = ?");
    $stmt->execute([$email]);
    $user = $stmt->fetch();
    
    if (!$user || !password_verify($password, $user['password_hash'])) {
        echo json_encode(['success' => false, 'message' => 'Неверный email или пароль']);
        exit;
    }
    
    // Удаляем старые сессии
    $stmt = $conn->prepare("DELETE FROM sessions WHERE user_id = ?");
    $stmt->execute([$user['user_id']]);
    
    // Создаем новую сессию
    $token = bin2hex(random_bytes(32));
    $session_id = generateUUID();
    $expires_at = date('Y-m-d H:i:s', strtotime('+30 days'));
    
    $stmt = $conn->prepare("INSERT INTO sessions (session_id, user_id, token, expires_at) VALUES (?, ?, ?, ?)");
    $stmt->execute([$session_id, $user['user_id'], $token, $expires_at]);
    
    // Устанавливаем куку
    setcookie('auth_token', $token, time() + 30*24*60*60, '/');
    
    // Запускаем сессию
    session_start();
    $_SESSION['user_id'] = $user['user_id'];
    
    // Убираем пароль из ответа
    unset($user['password_hash']);
    
    echo json_encode([
        'success' => true,
        'message' => 'Вход выполнен успешно',
        'data' => [
            'token' => $token,
            'user' => $user
        ]
    ]);
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Ошибка сервера: ' . $e->getMessage()]);
}
?>