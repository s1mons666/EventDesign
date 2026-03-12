<?php
// api/auth/login.php
header('Content-Type: application/json');

try {
    require_once __DIR__ . '/../config/database.php';
    require_once __DIR__ . '/../helpers/uuid.php';
    
    $data = json_decode(file_get_contents('php://input'), true);
    
    if (!$data) {
        throw new Exception('Invalid request data');
    }
    
    if (empty($data['email']) || empty($data['password'])) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Email и пароль обязательны']);
        exit;
    }
    
    $db = Database::getInstance();
    $conn = $db->getConnection();
    
    // Поиск пользователя
    $stmt = $conn->prepare("SELECT * FROM users WHERE email = ?");
    $stmt->execute([$data['email']]);
    $user = $stmt->fetch();
    
    if (!$user || !password_verify($data['password'], $user['password_hash'])) {
        http_response_code(401);
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
    
    $stmt = $conn->prepare("
        INSERT INTO sessions (session_id, user_id, token, expires_at) 
        VALUES (?, ?, ?, ?)
    ");
    $stmt->execute([$session_id, $user['user_id'], $token, $expires_at]);
    
    // Установка куки (если запомнить)
    if (!empty($data['rememberMe'])) {
        setcookie('auth_token', $token, time() + 30*24*60*60, '/');
    } else {
        setcookie('auth_token', $token, 0, '/'); // сессионная кука
    }
    
    // Удаляем пароль из ответа
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
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>