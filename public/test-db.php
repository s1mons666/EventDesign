<?php
// public/test-db.php
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<!DOCTYPE html>
<html>
<head>
    <meta charset='UTF-8'>
    <title>Тест базы данных</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Segoe UI', Arial; background: #6f6177; padding: 20px; }
        .container { max-width: 800px; margin: 50px auto; background: #eee; padding: 40px; border-radius: 16px; }
        h1 { color: #6f6177; margin-bottom: 30px; border-bottom: 2px solid #f5a7ff; padding-bottom: 10px; }
        h2 { color: #6f6177; margin: 25px 0 15px; }
        .success { background: #cfe3d8; color: #2d5a3a; padding: 15px; border-radius: 8px; margin: 10px 0; }
        .error { background: #eacaca; color: #a13e3e; padding: 15px; border-radius: 8px; margin: 10px 0; }
        .info { background: #cdd3e6; color: #3a4e6b; padding: 15px; border-radius: 8px; margin: 10px 0; }
        .btn { display: inline-block; padding: 12px 25px; background: #f5a7ff; color: #6f6177; text-decoration: none; border-radius: 8px; margin: 10px 5px; font-weight: bold; }
        table { width: 100%; border-collapse: collapse; margin: 15px 0; background: white; border-radius: 8px; overflow: hidden; }
        th { background: #6f6177; color: white; padding: 12px; text-align: left; }
        td { padding: 10px; border-bottom: 1px solid #ddd; }
    </style>
</head>
<body>
    <div class='container'>
        <h1>🔍 Тест базы данных</h1>";

try {
    require_once __DIR__ . '/../api/config/database.php';
    
    $db = Database::getInstance();
    $conn = $db->getConnection();
    
    echo "<div class='success'>✅ Подключение к БД успешно</div>";
    
    // Проверяем таблицы
    $tables = $conn->query("SELECT name FROM sqlite_master WHERE type='table' ORDER BY name");
    
    echo "<h2>📊 Таблицы в базе данных:</h2>";
    echo "<table>";
    echo "<tr><th>Название таблицы</th><th>Количество записей</th></tr>";
    
    $hasTables = false;
    foreach ($tables as $table) {
        $count = $conn->query("SELECT COUNT(*) as cnt FROM " . $table['name'])->fetch();
        echo "<tr><td>" . $table['name'] . "</td><td>" . $count['cnt'] . "</td></tr>";
        $hasTables = true;
    }
    echo "</table>";
    
    if (!$hasTables) {
        echo "<div class='error'>❌ Таблицы не найдены! Запустите install.php</div>";
    } else {
        // Проверяем тестового пользователя
        $stmt = $conn->prepare("SELECT * FROM users WHERE email = ?");
        $stmt->execute(['test@test.com']);
        $user = $stmt->fetch();
        
        if ($user) {
            echo "<div class='success'>✅ Тестовый пользователь существует</div>";
            echo "<table>";
            echo "<tr><th>Поле</th><th>Значение</th></tr>";
            echo "<tr><td>Email</td><td>" . $user['email'] . "</td></tr>";
            echo "<tr><td>Имя</td><td>" . $user['first_name'] . " " . $user['last_name'] . "</td></tr>";
            echo "<tr><td>Телефон</td><td>" . ($user['phone'] ?? 'Не указан') . "</td></tr>";
            echo "</table>";
        } else {
            echo "<div class='error'>❌ Тестовый пользователь не найден</div>";
        }
        
        // Проверяем мероприятия
        $events = $conn->query("SELECT COUNT(*) as cnt FROM events")->fetch();
        if ($events['cnt'] > 0) {
            echo "<div class='success'>✅ Найдено мероприятий: " . $events['cnt'] . "</div>";
            
            $events_list = $conn->query("SELECT * FROM events LIMIT 3");
            echo "<table>";
            echo "<tr><th>Название</th><th>Дата</th><th>Статус</th></tr>";
            foreach ($events_list as $event) {
                echo "<tr>";
                echo "<td>" . $event['title'] . "</td>";
                echo "<td>" . date('d.m.Y', strtotime($event['event_date'])) . "</td>";
                echo "<td>" . $event['status'] . "</td>";
                echo "</tr>";
            }
            echo "</table>";
        }
    }
    
    echo "<div style='margin-top:30px; text-align:center;'>";
    echo "<a href='install.php' class='btn'>🔄 Переустановить БД</a> ";
    echo "<a href='login.php' class='btn'>🔑 Войти</a>";
    echo "</div>";
    
} catch (Exception $e) {
    echo "<div class='error'>❌ Ошибка: " . $e->getMessage() . "</div>";
}

echo "</div></body></html>";
?>