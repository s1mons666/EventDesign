<?php
// public/check-handler.php
echo "<h1>Проверка обработчика профиля</h1>";

$handler_path = __DIR__ . '/update-profile-handler.php';

if (file_exists($handler_path)) {
    echo "<p style='color:green'>✅ Файл существует: $handler_path</p>";
    
    // Проверяем, что файл читается
    $content = file_get_contents($handler_path);
    if (strpos($content, '<?php') !== false) {
        echo "<p style='color:green'>✅ Файл содержит PHP код</p>";
    } else {
        echo "<p style='color:red'>❌ Файл не содержит PHP код</p>";
    }
} else {
    echo "<p style='color:red'>❌ Файл НЕ существует по пути: $handler_path</p>";
    echo "<p>Создайте файл по этому пути</p>";
}

// Проверяем альтернативные пути
$alt_path1 = __DIR__ . '/../api/profile/update-profile-handler.php';
$alt_path2 = __DIR__ . '/../api/auth/update-profile-handler.php';

echo "<h2>Альтернативные пути:</h2>";
echo "<p>$alt_path1 - " . (file_exists($alt_path1) ? '✅' : '❌') . "</p>";
echo "<p>$alt_path2 - " . (file_exists($alt_path2) ? '✅' : '❌') . "</p>";

echo "<p><a href='profile.php'>Вернуться в профиль</a></p>";
?>