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
    
    // Логируем полученные данные (для отладки)
    error_log("Profile update attempt for user: $user_id");
    error_log("POST data: " . print_r($_POST, true));
    
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
    
    // Валидация
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
    
    // Обновление пароля
    $password_hash = $user['password_hash'];
    
    if (!empty($new_password)) {
        // Проверка старого пароля
        if (empty($current_password)) {
            echo json_encode(['success' => false, 'message' => 'Введите текущий пароль']);
            exit;
        }
        
        if (!password_verify($current_password, $user['password_hash'])) {
            echo json_encode(['success' => false, 'message' => 'Неверный текущий пароль']);
            exit;
        }
        
        if (strlen($new_password) < 6) {
            echo json_encode(['success' => false, 'message' => 'Новый пароль должен быть минимум 6 символов']);
            exit;
        }
        
        if ($new_password !== $confirm_password) {
            echo json_encode(['success' => false, 'message' => 'Пароли не совпадают']);
            exit;
        }
        
        $password_hash = password_hash($new_password, PASSWORD_DEFAULT);
    }
    
    // Обновляем данные
    $stmt = $conn->prepare("
        UPDATE users 
        SET first_name = ?, last_name = ?, email = ?, phone = ?, password_hash = ?
        WHERE user_id = ?
    ");
    
    $result = $stmt->execute([
        $first_name,
        $last_name,
        $email,
        $phone ?: null,
        $password_hash,
        $user_id
    ]);
    
    if ($result) {
        echo json_encode([
            'success' => true,
            'message' => 'Профиль успешно обновлен',
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
    echo json_encode(['success' => false, 'message' => 'Ошибка базы данных: ' . $e->getMessage()]);
} catch (Exception $e) {
    error_log("General error in profile update: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Ошибка: ' . $e->getMessage()]);
}
?>