<?php
// public/update-profile-handler.php
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
    $first_name = $_POST['first_name'] ?? '';
    $last_name = $_POST['last_name'] ?? '';
    $email = $_POST['email'] ?? '';
    $phone = $_POST['phone'] ?? '';
    $current_password = $_POST['current_password'] ?? '';
    $new_password = $_POST['new_password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    
    // Логируем для отладки
    error_log("Profile update attempt for user: $user_id");
    error_log("Password change requested: " . (!empty($new_password) ? 'YES' : 'NO'));
    
    $db = Database::getInstance();
    $conn = $db->getConnection();
    
    // Получаем текущие данные пользователя
    $stmt = $conn->prepare("SELECT * FROM users WHERE user_id = ?");
    $stmt->execute([$user_id]);
    $user = $stmt->fetch();
    
    if (!$user) {
        echo json_encode(['success' => false, 'message' => 'Пользователь не найден']);
        exit;
    }
    
    // Валидация основных полей
    if (empty($first_name)) {
        echo json_encode(['success' => false, 'message' => 'Имя обязательно']);
        exit;
    }
    
    if (empty($last_name)) {
        echo json_encode(['success' => false, 'message' => 'Фамилия обязательна']);
        exit;
    }
    
    if (empty($email)) {
        echo json_encode(['success' => false, 'message' => 'Email обязателен']);
        exit;
    }
    
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        echo json_encode(['success' => false, 'message' => 'Неверный формат email']);
        exit;
    }
    
    // Проверка уникальности email (если email изменился)
    if ($email !== $user['email']) {
        $stmt = $conn->prepare("SELECT user_id FROM users WHERE email = ? AND user_id != ?");
        $stmt->execute([$email, $user_id]);
        if ($stmt->fetch()) {
            echo json_encode(['success' => false, 'message' => 'Этот email уже используется']);
            exit;
        }
    }
    
    // ИСПРАВЛЕННАЯ ЛОГИКА СМЕНЫ ПАРОЛЯ
    $password_hash = $user['password_hash'];
    $password_changed = false;
    
    if (!empty($new_password)) {
        // Проверка что все поля для смены пароля заполнены
        if (empty($current_password)) {
            echo json_encode(['success' => false, 'message' => 'Введите текущий пароль']);
            exit;
        }
        
        // Проверка текущего пароля
        if (!password_verify($current_password, $user['password_hash'])) {
            echo json_encode(['success' => false, 'message' => 'Неверный текущий пароль']);
            exit;
        }
        
        // Проверка длины нового пароля
        if (strlen($new_password) < 6) {
            echo json_encode(['success' => false, 'message' => 'Новый пароль должен быть минимум 6 символов']);
            exit;
        }
        
        // Проверка совпадения паролей
        if ($new_password !== $confirm_password) {
            echo json_encode(['success' => false, 'message' => 'Новый пароль и подтверждение не совпадают']);
            exit;
        }
        
        // Хешируем новый пароль
        $password_hash = password_hash($new_password, PASSWORD_DEFAULT);
        $password_changed = true;
        
        error_log("Password will be changed for user: $user_id");
    }
    
    // Обновляем данные в базе
    $sql = "UPDATE users SET first_name = ?, last_name = ?, email = ?, phone = ?, password_hash = ? WHERE user_id = ?";
    $stmt = $conn->prepare($sql);
    $result = $stmt->execute([
        $first_name,
        $last_name,
        $email,
        $phone ?: null,
        $password_hash,
        $user_id
    ]);
    
    if ($result) {
        $message = $password_changed ? 'Профиль и пароль успешно обновлены' : 'Профиль успешно обновлен';
        
        // Если пароль был изменен, удаляем все старые сессии кроме текущей (для безопасности)
        if ($password_changed) {
            // Удаляем все сессии пользователя
            $stmt = $conn->prepare("DELETE FROM sessions WHERE user_id = ?");
            $stmt->execute([$user_id]);
            
            // Создаем новую сессию с новым токеном
            $token = bin2hex(random_bytes(32));
            $session_id = bin2hex(random_bytes(16));
            $expires_at = date('Y-m-d H:i:s', strtotime('+30 days'));
            
            $stmt = $conn->prepare("INSERT INTO sessions (session_id, user_id, token, expires_at) VALUES (?, ?, ?, ?)");
            $stmt->execute([$session_id, $user_id, $token, $expires_at]);
            
            // Обновляем куку
            setcookie('auth_token', $token, time() + 30*24*60*60, '/');
        }
        
        echo json_encode([
            'success' => true,
            'message' => $message,
            'password_changed' => $password_changed,
            'user' => [
                'first_name' => $first_name,
                'last_name' => $last_name,
                'email' => $email,
                'phone' => $phone
            ]
        ]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Ошибка при обновлении профиля']);
    }
    
} catch (PDOException $e) {
    error_log("Database error in profile update: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Ошибка базы данных']);
} catch (Exception $e) {
    error_log("General error in profile update: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Ошибка сервера']);
}
?>