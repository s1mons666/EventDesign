<?php
// api/auth/logout.php
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
    
    if ($token) {
        $db = Database::getInstance();
        $conn = $db->getConnection();
        
        // Удаляем сессию
        $stmt = $conn->prepare("DELETE FROM sessions WHERE token = ?");
        $stmt->execute([$token]);
    }
    
    // Удаляем куки
    setcookie('auth_token', '', time() - 3600, '/');
    
    echo json_encode(['success' => true, 'message' => 'Выход выполнен']);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>