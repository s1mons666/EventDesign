<?php
// public/update-database.php
require_once __DIR__ . '/../api/config/database.php';

echo "<!DOCTYPE html>
<html>
<head>
    <meta charset='UTF-8'>
    <title>Обновление базы данных</title>
    <style>
        body { font-family: Arial; background: #6f6177; padding: 20px; }
        .container { max-width: 600px; margin: 0 auto; background: #eee; padding: 30px; border-radius: 16px; }
        .success { color: green; background: #cfe3d8; padding: 10px; border-radius: 8px; margin: 10px 0; }
        .error { color: red; background: #eacaca; padding: 10px; border-radius: 8px; margin: 10px 0; }
        .info { background: #cdd3e6; padding: 10px; border-radius: 8px; margin: 10px 0; }
        .btn { display: inline-block; padding: 10px 20px; background: #f5a7ff; color: white; text-decoration: none; border-radius: 8px; margin-top: 20px; }
    </style>
</head>
<body>
    <div class='container'>
        <h1>🔄 Обновление базы данных</h1>";

try {
    $db = Database::getInstance();
    $conn = $db->getConnection();
    
    echo "<div class='info'>📡 Подключение к базе данных успешно</div>";
    
    // Проверяем, есть ли уже колонка photo_path
    $columns = $conn->query("PRAGMA table_info(events)");
    $has_photo_path = false;
    $has_photo_name = false;
    
    while ($col = $columns->fetch()) {
        if ($col['name'] == 'photo_path') $has_photo_path = true;
        if ($col['name'] == 'photo_name') $has_photo_name = true;
    }
    
    // Добавляем недостающие колонки
    if (!$has_photo_path) {
        $conn->exec("ALTER TABLE events ADD COLUMN photo_path TEXT");
        echo "<div class='success'>✅ Добавлена колонка photo_path</div>";
    } else {
        echo "<div class='info'>📌 Колонка photo_path уже существует</div>";
    }
    
    if (!$has_photo_name) {
        $conn->exec("ALTER TABLE events ADD COLUMN photo_name TEXT");
        echo "<div class='success'>✅ Добавлена колонка photo_name</div>";
    } else {
        echo "<div class='info'>📌 Колонка photo_name уже существует</div>";
    }
    
    // Проверяем структуру таблицы
    echo "<h2>📊 Текущая структура таблицы events:</h2>";
    $columns = $conn->query("PRAGMA table_info(events)");
    echo "<table style='width:100%; border-collapse:collapse; background:white; border-radius:8px; overflow:hidden;'>";
    echo "<tr style='background:#6f6177; color:white;'><th>Колонка</th><th>Тип</th><th>Описание</th></tr>";
    
    while ($col = $columns->fetch()) {
        $color = ($col['name'] == 'photo_path' || $col['name'] == 'photo_name') ? '#f5a7ff' : 'transparent';
        echo "<tr style='background: $color;'>";
        echo "<td style='padding:8px;'>" . $col['name'] . "</td>";
        echo "<td style='padding:8px;'>" . $col['type'] . "</td>";
        echo "<td style='padding:8px;'>" . ($col['notnull'] ? 'NOT NULL' : 'NULL') . "</td>";
        echo "</tr>";
    }
    echo "</table>";
    
    echo "<div style='margin-top:30px; text-align:center;'>
        <a href='event-create.php' class='btn'>✨ Создать мероприятие</a>
        <a href='dashboard.php' class='btn'>📅 Перейти к мероприятиям</a>
    </div>";
    
} catch (PDOException $e) {
    echo "<div class='error'>❌ Ошибка: " . $e->getMessage() . "</div>";
}

echo "</div></body></html>";
?>