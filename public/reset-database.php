<?php
// public/reset-database.php - ПОЛНОЕ ПЕРЕСОЗДАНИЕ БД
session_start();

// Очищаем сессию
$_SESSION = array();
session_destroy();

// Удаляем куки авторизации
setcookie('auth_token', '', time() - 3600, '/');

// Путь к файлу базы данных
$db_file = __DIR__ . '/../database/eventdesign.sqlite';
$schema_file = __DIR__ . '/../database/schema.sql';

echo '<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Пересоздание базы данных</title>
    <style>
        body { font-family: Arial; background: #6f6177; padding: 20px; }
        .container { max-width: 600px; margin: 50px auto; background: #eee; padding: 30px; border-radius: 16px; }
        .success { color: green; background: #cfe3d8; padding: 10px; border-radius: 8px; }
        .error { color: red; background: #eacaca; padding: 10px; border-radius: 8px; }
        .btn { display: inline-block; padding: 10px 20px; background: #f5a7ff; color: white; text-decoration: none; border-radius: 8px; margin-top: 20px; }
    </style>
</head>
<body>
    <div class="container">';

// Проверяем, есть ли подтверждение
if (!isset($_GET['confirm'])) {
    echo '<h2>⚠️ Внимание!</h2>';
    echo '<p>Вы собираетесь ПОЛНОСТЬЮ удалить базу данных. Все пользователи, мероприятия и задачи будут потеряны.</p>';
    echo '<p>Это действие нельзя отменить!</p>';
    echo '<a href="?confirm=yes" class="btn" style="background:#eacaca; color:#6f6177;">🗑️ Да, удалить всё</a> ';
    echo '<a href="dashboard.php" class="btn">🔙 Отмена</a>';
    exit;
}

// Удаляем существующий файл БД
if (file_exists($db_file)) {
    if (unlink($db_file)) {
        echo '<p class="success">✅ Старый файл базы данных удален</p>';
    } else {
        echo '<p class="error">❌ Ошибка при удалении файла базы данных</p>';
    }
} else {
    echo '<p>📁 Файл базы данных не найден. Будет создан новый.</p>';
}

// Подключаемся к БД (создаст новый файл)
try {
    require_once __DIR__ . '/../api/config/database.php';
    
    $db = Database::getInstance();
    $conn = $db->getConnection();
    
    echo '<p class="success">✅ Новая база данных создана</p>';
    
    // Проверяем созданные таблицы
    $tables = $conn->query("SELECT name FROM sqlite_master WHERE type='table'");
    echo '<h3>📊 Созданные таблицы:</h3><ul>';
    foreach ($tables as $table) {
        echo '<li>' . $table['name'] . '</li>';
    }
    echo '</ul>';
    
    // Проверяем тестовые данные
    $users = $conn->query("SELECT COUNT(*) as count FROM users")->fetch();
    $events = $conn->query("SELECT COUNT(*) as count FROM events")->fetch();
    
    echo '<p>👥 Пользователей: ' . $users['count'] . '</p>';
    echo '<p>📅 Мероприятий: ' . $events['count'] . '</p>';
    
    echo '<p><a href="login.php" class="btn">🔑 Перейти к входу</a> ';
    echo '<a href="register.php" class="btn">📝 Зарегистрироваться</a></p>';
    
} catch (Exception $e) {
    echo '<p class="error">❌ Ошибка: ' . $e->getMessage() . '</p>';
}

echo '</div></body></html>';
?>