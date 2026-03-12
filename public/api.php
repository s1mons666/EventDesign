<?php
// public/api.php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');
header('Access-Control-Allow-Credentials: true');
header('Access-Control-Max-Age: 86400'); // 24 часа

// Обработка OPTIONS запросов (preflight)
if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Функция для логирования
function logMessage($message) {
    $logFile = __DIR__ . '/../logs/api.log';
    $logDir = dirname($logFile);
    
    if (!file_exists($logDir)) {
        mkdir($logDir, 0777, true);
    }
    
    $timestamp = date('Y-m-d H:i:s');
    file_put_contents($logFile, "[$timestamp] $message\n", FILE_APPEND);
}

// Логируем запрос
logMessage("Request: {$_SERVER['REQUEST_METHOD']} {$_SERVER['REQUEST_URI']}");

// Функция для получения user_id из токена
function getUserIdFromToken() {
    $headers = getallheaders();
    $token = null;
    
    // Нормализуем заголовки (иногда они приходят в разном регистре)
    $headers = array_change_key_case($headers, CASE_LOWER);
    
    // Получаем токен из заголовка Authorization
    if (isset($headers['authorization'])) {
        $auth = $headers['authorization'];
        $token = str_replace('Bearer ', '', $auth);
        logMessage("Token from header: " . substr($token, 0, 10) . "...");
    } 
    // Или из cookie
    elseif (isset($_COOKIE['auth_token'])) {
        $token = $_COOKIE['auth_token'];
        logMessage("Token from cookie: " . substr($token, 0, 10) . "...");
    }
    
    if (!$token) {
        logMessage("No token found");
        return null;
    }
    
    require_once __DIR__ . '/../api/config/database.php';
    $db = Database::getInstance();
    $conn = $db->getConnection();
    
    $stmt = $conn->prepare("SELECT user_id FROM sessions WHERE token = ? AND expires_at > datetime('now')");
    $stmt->execute([$token]);
    $session = $stmt->fetch();
    
    if ($session) {
        logMessage("User authenticated: " . $session['user_id']);
        return $session['user_id'];
    } else {
        logMessage("Invalid or expired token");
        return null;
    }
}

$route = $_GET['route'] ?? '';
$method = $_SERVER['REQUEST_METHOD'];

logMessage("Route: $route, Method: $method");

try {
    // Auth routes (не требуют авторизации)
    if ($route === 'auth/register') {
        require_once __DIR__ . '/../api/auth/register.php';
    }
    elseif ($route === 'auth/login') {
        require_once __DIR__ . '/../api/auth/login.php';
    }
    elseif ($route === 'auth/check') {
        require_once __DIR__ . '/../api/auth/check.php';
    }
    elseif ($route === 'auth/logout') {
        require_once __DIR__ . '/../api/auth/logout.php';
    }
    
    // Routes that require authentication
    else {
        $user_id = getUserIdFromToken();
        
        if (!$user_id) {
            http_response_code(401);
            echo json_encode(['success' => false, 'message' => 'Требуется авторизация']);
            exit;
        }
        
        // Передаем user_id в GET или POST
        $_GET['user_id'] = $user_id;
        $_POST['user_id'] = $user_id;
        
        // Events routes
        if ($route === 'events/index') {
            if ($method === 'GET') {
                require_once __DIR__ . '/../api/events/index.php';
            } elseif ($method === 'POST') {
                require_once __DIR__ . '/../api/events/create.php';
            } else {
                http_response_code(405);
                echo json_encode(['success' => false, 'message' => 'Метод не поддерживается']);
            }
        }
        elseif ($route === 'events/get') {
            require_once __DIR__ . '/../api/events/get.php';
        }
        elseif ($route === 'events/update') {
            require_once __DIR__ . '/../api/events/update.php';
        }
        elseif ($route === 'events/delete') {
            require_once __DIR__ . '/../api/events/delete.php';
        }
        
        // Tasks routes
        elseif ($route === 'tasks/index') {
            require_once __DIR__ . '/../api/tasks/index.php';
        }
        elseif ($route === 'tasks/create') {
            require_once __DIR__ . '/../api/tasks/create.php';
        }
        elseif ($route === 'tasks/update') {
            require_once __DIR__ . '/../api/tasks/update.php';
        }
        elseif ($route === 'tasks/delete') {
            require_once __DIR__ . '/../api/tasks/delete.php';
        }
        
        // Not found
        else {
            http_response_code(404);
            echo json_encode(['success' => false, 'message' => 'Маршрут не найден: ' . $route]);
        }
    }
    
} catch (Exception $e) {
    logMessage("Error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Внутренняя ошибка сервера: ' . $e->getMessage()]);
}
?>