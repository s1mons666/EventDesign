<?php
// api/profile/update.php
require_once __DIR__ . '/../config/database.php';

$db = Database::getInstance();
$conn = $db->getConnection();
$user_id = $db->requireAuth();
$input = $db->getInput();

$errors = [];

if (empty($input['first_name'])) $errors['first_name'] = 'Имя обязательно';
if (empty($input['last_name'])) $errors['last_name'] = 'Фамилия обязательна';
if (empty($input['email'])) $errors['email'] = 'Email обязателен';
elseif (!filter_var($input['email'], FILTER_VALIDATE_EMAIL)) $errors['email'] = 'Неверный формат email';

// Проверка уникальности email (если он изменился)
if (!empty($input['email'])) {
    $stmt = $conn->prepare("SELECT user_id FROM users WHERE email = ? AND user_id != ?");
    $stmt->execute([$input['email'], $user_id]);
    if ($stmt->fetch()) {
        $errors['email'] = 'Email уже используется';
    }
}

// Если меняется пароль
if (!empty($input['new_password'])) {
    if (strlen($input['new_password']) < 6) {
        $errors['new_password'] = 'Пароль должен быть минимум 6 символов';
    }
}

if (!empty($errors)) {
    $db->sendError('Ошибка валидации', 422, $errors);
}

// Обновляем основные данные
$stmt = $conn->prepare("UPDATE users SET first_name = ?, last_name = ?, email = ?, phone = ? WHERE user_id = ?");
$stmt->execute([
    $input['first_name'],
    $input['last_name'],
    $input['email'],
    $input['phone'] ?? null,
    $user_id
]);

// Если меняется пароль
if (!empty($input['new_password'])) {
    $password_hash = password_hash($input['new_password'], PASSWORD_DEFAULT);
    $stmt = $conn->prepare("UPDATE users SET password_hash = ? WHERE user_id = ?");
    $stmt->execute([$password_hash, $user_id]);
}

$db->sendSuccess([
    'user_id' => $user_id,
    'first_name' => $input['first_name'],
    'last_name' => $input['last_name'],
    'email' => $input['email'],
    'phone' => $input['phone'] ?? null
], 'Профиль обновлен');
?>