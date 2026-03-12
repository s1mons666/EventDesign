<?php
// public/install.php
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<!DOCTYPE html>
<html>
<head>
    <meta charset='UTF-8'>
    <title>Установка EventDesign</title>
    <style>
        body { font-family: Arial; background: #6f6177; padding: 20px; }
        .container { max-width: 800px; margin: 0 auto; background: #eee; padding: 30px; border-radius: 16px; }
        .success { color: green; background: #cfe3d8; padding: 10px; border-radius: 8px; margin: 10px 0; }
        .error { color: red; background: #eacaca; padding: 10px; border-radius: 8px; margin: 10px 0; }
        .info { background: #cdd3e6; padding: 10px; border-radius: 8px; margin: 10px 0; }
        .btn { display: inline-block; padding: 10px 20px; background: #f5a7ff; color: white; text-decoration: none; border-radius: 8px; margin-top: 20px; }
    </style>
</head>
<body>
    <div class='container'>
        <h1>🔧 Установка EventDesign</h1>";

// Путь к базе данных
$db_dir = __DIR__ . '/../database';
$db_file = $db_dir . '/eventdesign.sqlite';

// Создаем папку database
if (!file_exists($db_dir)) {
    mkdir($db_dir, 0777, true);
    echo "<div class='success'>✅ Создана папка database</div>";
}

// Создаем папку для загрузки фото
$upload_dir = __DIR__ . '/uploads/events';
if (!file_exists($upload_dir)) {
    mkdir($upload_dir, 0777, true);
    echo "<div class='success'>✅ Создана папка для загрузки фото</div>";
}

// Удаляем старую базу данных
if (file_exists($db_file)) {
    unlink($db_file);
    echo "<div class='success'>✅ Старая база данных удалена</div>";
}

try {
    // Создаем новое подключение
    $conn = new PDO("sqlite:" . $db_file);
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    echo "<div class='success'>✅ Создана новая база данных</div>";
    
    // Создаем таблицы с полем для фото
    $conn->exec("
        -- Таблица пользователей
        CREATE TABLE IF NOT EXISTS users (
            user_id TEXT PRIMARY KEY,
            email TEXT UNIQUE NOT NULL,
            password_hash TEXT NOT NULL,
            first_name TEXT NOT NULL,
            last_name TEXT NOT NULL,
            phone TEXT,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
        );
        
        -- Таблица сессий
        CREATE TABLE IF NOT EXISTS sessions (
            session_id TEXT PRIMARY KEY,
            user_id TEXT NOT NULL,
            token TEXT UNIQUE NOT NULL,
            expires_at DATETIME NOT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE
        );
        
        -- Таблица мероприятий с полями для фото
        CREATE TABLE IF NOT EXISTS events (
            event_id TEXT PRIMARY KEY,
            user_id TEXT NOT NULL,
            title TEXT NOT NULL,
            event_date DATETIME NOT NULL,
            location TEXT,
            budget DECIMAL(10,2),
            status TEXT DEFAULT 'draft',
            description TEXT,
            photo_path TEXT,
            photo_name TEXT,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE
        );
        
        -- Таблица задач
        CREATE TABLE IF NOT EXISTS tasks (
            task_id TEXT PRIMARY KEY,
            event_id TEXT NOT NULL,
            title TEXT NOT NULL,
            cost DECIMAL(10,2) DEFAULT 0,
            is_completed INTEGER DEFAULT 0,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (event_id) REFERENCES events(event_id) ON DELETE CASCADE
        );
        
        -- Индексы
        CREATE INDEX IF NOT EXISTS idx_users_email ON users(email);
        CREATE INDEX IF NOT EXISTS idx_sessions_token ON sessions(token);
        CREATE INDEX IF NOT EXISTS idx_events_user_id ON events(user_id);
        CREATE INDEX IF NOT EXISTS idx_events_date ON events(event_date);
    ");
    
    echo "<div class='success'>✅ Таблицы созданы успешно</div>";
    
    // Создаем тестового пользователя
    $user_id = '11111111-1111-1111-1111-111111111111';
    $password_hash = password_hash('123456', PASSWORD_DEFAULT);
    
    $stmt = $conn->prepare("INSERT INTO users (user_id, email, password_hash, first_name, last_name, phone) VALUES (?, ?, ?, ?, ?, ?)");
    $stmt->execute([$user_id, 'test@test.com', $password_hash, 'Тест', 'Пользователь', '+7 (999) 123-45-67']);
    
    echo "<div class='success'>✅ Тестовый пользователь создан</div>";
    echo "<div class='info'><strong>Email:</strong> test@test.com<br><strong>Пароль:</strong> 123456</div>";
    
    // Создаем тестовое мероприятие
    $event_id = '22222222-2222-2222-2222-222222222222';
    $future_date = date('Y-m-d H:i:s', strtotime('+30 days'));
    
    $stmt = $conn->prepare("
        INSERT INTO events (event_id, user_id, title, event_date, location, budget, status, description, photo_path, photo_name) 
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");
    
    $stmt->execute([
        $event_id,
        $user_id,
        'Тестовая свадьба',
        $future_date,
        'Ресторан Престиж',
        300000,
        'planned',
        'Тестовое мероприятие для проверки',
        null,
        null
    ]);
    
    echo "<div class='success'>✅ Тестовое мероприятие создано</div>";
    
    // Создаем тестовые задачи
    $tasks = [
        ['Заказать торт', 15000, 0],
        ['Пригласить фотографа', 25000, 0],
        ['Забронировать зал', 0, 1],
        ['Купить цветы', 8000, 0]
    ];
    
    $stmt = $conn->prepare("INSERT INTO tasks (task_id, event_id, title, cost, is_completed) VALUES (?, ?, ?, ?, ?)");
    
    foreach ($tasks as $task) {
        $task_id = uniqid('task_', true);
        $stmt->execute([$task_id, $event_id, $task[0], $task[1], $task[2]]);
    }
    
    echo "<div class='success'>✅ Тестовые задачи созданы</div>";
    
    echo "<div style='margin-top:30px; text-align:center;'>
        <a href='login.php' class='btn'>🔑 Перейти к входу</a>
        <a href='dashboard.php' class='btn'>📅 Перейти к мероприятиям</a>
    </div>";
    
} catch (PDOException $e) {
    echo "<div class='error'>❌ Ошибка: " . $e->getMessage() . "</div>";
}

echo "</div></body></html>";
?>