<?php
// api/auth/check.php
header('Content-Type: application/json');

try {
    require_once __DIR__ . '/../config/database.php';
    
    $headers = getallheaders();
    $token = null;
    
    if (isset($headers['Authorization'])) {
        $auth = $headers['Authorization'];
        $token = str_replace('Bearer ', '', $auth);
    } elseif (isset($_COOKIE['auth_token'])) {
        $token = $_COOKIE['auth_token'];
    }
    
    if (!$token) {
        http_response_code(401);
        echo json_encode(['success' => false, 'message' => 'Не авторизован']);
        exit;
    }
    
    $db = Database::getInstance();
    $conn = $db->getConnection();
    
    // Проверка сессии
    $stmt = $conn->prepare("
        SELECT s.*, u.email, u.first_name, u.last_name, u.phone 
        FROM sessions s
        JOIN users u ON s.user_id = u.user_id
        WHERE s.token = ? AND s.expires_at > datetime('now')
    ");
    $stmt->execute([$token]);
    $session = $stmt->fetch();
    
    if (!$session) {
        http_response_code(401);
        echo json_encode(['success' => false, 'message' => 'Сессия истекла']);
        exit;
    }
    
    echo json_encode([
        'success' => true,
        'data' => [
            'user_id' => $session['user_id'],
            'email' => $session['email'],
            'first_name' => $session['first_name'],
            'last_name' => $session['last_name'],
            'phone' => $session['phone']
        ]
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>