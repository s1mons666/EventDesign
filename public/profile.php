<?php
// public/profile.php
session_start();
require_once __DIR__ . '/../api/config/database.php';

$db = Database::getInstance();
$conn = $db->getConnection();

// Проверка авторизации
$user_id = null;

if (isset($_SESSION['user_id'])) {
    $user_id = $_SESSION['user_id'];
} elseif (isset($_COOKIE['auth_token'])) {
    $token = $_COOKIE['auth_token'];
    $stmt = $conn->prepare("SELECT user_id FROM sessions WHERE token = ? AND expires_at > datetime('now')");
    $stmt->execute([$token]);
    $session = $stmt->fetch();
    if ($session) {
        $user_id = $session['user_id'];
        $_SESSION['user_id'] = $user_id;
    }
}

if (!$user_id) {
    header('Location: login.php');
    exit;
}

// Получаем данные пользователя
$stmt = $conn->prepare("SELECT * FROM users WHERE user_id = ?");
$stmt->execute([$user_id]);
$user = $stmt->fetch();

// Получаем статистику
$stmt = $conn->prepare("SELECT COUNT(*) as events_count FROM events WHERE user_id = ?");
$stmt->execute([$user_id]);
$events_count = $stmt->fetch()['events_count'];

$stmt = $conn->prepare("
    SELECT COUNT(*) as tasks_count 
    FROM tasks t
    JOIN events e ON t.event_id = e.event_id
    WHERE e.user_id = ?
");
$stmt->execute([$user_id]);
$tasks_count = $stmt->fetch()['tasks_count'];
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <title>Мой профиль - EventDesign</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="css/style.css">
    <style>
        .profile-container {
            max-width: 600px;
            margin: 40px auto;
            background: #eee;
            padding: 40px;
            border-radius: 16px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.2);
        }
        
        .profile-header {
            display: flex;
            align-items: center;
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .profile-avatar {
            width: 80px;
            height: 80px;
            background: #f5a7ff;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 32px;
            color: #6f6177;
            font-weight: bold;
        }
        
        .profile-name h2 {
            color: #6f6177;
            margin-bottom: 5px;
        }
        
        .profile-name p {
            color: #666;
        }
        
        .stats-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 15px;
            margin-bottom: 30px;
        }
        
        .stat-card {
            background: white;
            padding: 20px;
            border-radius: 12px;
            text-align: center;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
        }
        
        .stat-number {
            font-size: 32px;
            font-weight: bold;
            color: #6f6177;
            margin-bottom: 5px;
        }
        
        .stat-label {
            color: #666;
            font-size: 14px;
        }
        
        .info-item {
            margin: 15px 0;
            padding: 10px;
            background: white;
            border-radius: 8px;
        }
        
        .info-item strong {
            color: #6f6177;
            display: inline-block;
            width: 120px;
        }
        
        .info-item span {
            color: #333;
        }
        
        .profile-actions {
            display: flex;
            gap: 10px;
            margin-top: 30px;
        }
        
        .profile-actions button {
            flex: 1;
            padding: 12px;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            transition: all 0.3s;
        }
        
        .profile-actions .dashboard-btn {
            background: #cdd3e6;
            color: #6f6177;
        }
        
        .profile-actions .home-btn {
            background: white;
            color: #6f6177;
        }
        
        .profile-actions button:hover {
            transform: translateY(-2px);
            filter: brightness(0.95);
        }
        
        hr {
            border: none;
            border-top: 2px solid #ddd;
            margin: 30px 0;
        }
    </style>
</head>
<body class="bg-city">
    <header class="header">
        <div class="container nav">
            <div class="logo">Event<span>Design</span></div>
            <nav class="menu">
                <a href="index.php">Главная</a>
                <a href="dashboard.php">Мои мероприятия</a>
                <a href="profile.php" style="color:#f5a7ff;">Профиль</a>
            </nav>
            <div class="auth-buttons">
                <span style="color:white; margin-right:15px;">
                    <?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name']); ?>
                </span>
                <button class="btn pink" onclick="logout()">Выход</button>
            </div>
        </div>
    </header>

    <div class="profile-container">
        <h1 style="margin-bottom:30px; color:#6f6177; text-align:center;">Мой профиль</h1>

        <div class="profile-header">
            <div class="profile-avatar">
                <?php echo strtoupper(mb_substr($user['first_name'], 0, 1) . mb_substr($user['last_name'], 0, 1)); ?>
            </div>
            <div class="profile-name">
                <h2><?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name']); ?></h2>
                <p><?php echo htmlspecialchars($user['email']); ?></p>
            </div>
        </div>

        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-number"><?php echo $events_count; ?></div>
                <div class="stat-label">Мероприятий</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?php echo $tasks_count; ?></div>
                <div class="stat-label">Задач</div>
            </div>
        </div>

        <div class="info-item">
            <strong>Email:</strong> <span><?php echo htmlspecialchars($user['email']); ?></span>
        </div>
        <div class="info-item">
            <strong>Телефон:</strong> <span><?php echo htmlspecialchars($user['phone'] ?? 'Не указан'); ?></span>
        </div>
        <div class="info-item">
            <strong>Дата регистрации:</strong> <span><?php echo date('d.m.Y', strtotime($user['created_at'])); ?></span>
        </div>

        <hr>

        <div class="profile-actions">
            <button class="dashboard-btn" onclick="location.href='dashboard.php'">📅 Мои мероприятия</button>
            <button class="home-btn" onclick="location.href='index.php'">🏠 На главную</button>
        </div>
    </div>

    <script>
        function logout() {
            if (confirm('Выйти из аккаунта?')) {
                window.location.href = 'logout.php';
            }
        }
    </script>
</body>
</html>