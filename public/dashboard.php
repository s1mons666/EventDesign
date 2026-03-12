<?php
// public/dashboard.php
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

if (!$user) {
    session_destroy();
    setcookie('auth_token', '', time() - 3600, '/');
    header('Location: login.php');
    exit;
}

// Получаем параметры фильтрации
$filter = isset($_GET['filter']) ? $_GET['filter'] : 'all';
$sort = isset($_GET['sort']) ? $_GET['sort'] : 'date_desc';

// Формируем запрос для получения мероприятий
$query = "SELECT * FROM events WHERE user_id = :user_id";
$params = [':user_id' => $user_id];

if ($filter == 'upcoming') {
    $query .= " AND event_date > datetime('now')";
} elseif ($filter == 'past') {
    $query .= " AND event_date < datetime('now')";
}

if ($sort == 'date_desc') {
    $query .= " ORDER BY event_date DESC";
} elseif ($sort == 'date_asc') {
    $query .= " ORDER BY event_date ASC";
} elseif ($sort == 'name') {
    $query .= " ORDER BY title ASC";
}

$stmt = $conn->prepare($query);
$stmt->execute($params);
$events = $stmt->fetchAll();

// Получаем статистику по задачам для каждого мероприятия
$events_with_stats = [];
foreach ($events as $event) {
    $stmt = $conn->prepare("
        SELECT 
            COUNT(*) as total_tasks,
            SUM(CASE WHEN is_completed = 1 THEN 1 ELSE 0 END) as completed_tasks,
            SUM(cost) as total_cost
        FROM tasks 
        WHERE event_id = ?
    ");
    $stmt->execute([$event['event_id']]);
    $stats = $stmt->fetch();
    
    $event['stats'] = $stats;
    $events_with_stats[] = $event;
}

// Получаем ближайшее мероприятие
$stmt = $conn->prepare("
    SELECT * FROM events 
    WHERE user_id = ? AND event_date > datetime('now') 
    ORDER BY event_date ASC LIMIT 1
");
$stmt->execute([$user_id]);
$next_event = $stmt->fetch();

// Функции для форматирования
function formatDate($dateString) {
    if (!$dateString) return 'Дата не указана';
    try {
        return date('d.m.Y H:i', strtotime($dateString));
    } catch (Exception $e) {
        return $dateString;
    }
}

function getStatusText($status) {
    $statuses = [
        'draft' => 'Черновик',
        'planned' => 'Запланировано',
        'in_progress' => 'В работе',
        'completed' => 'Завершено',
        'cancelled' => 'Отменено'
    ];
    return $statuses[$status] ?? $status;
}

function getStatusColor($status) {
    $colors = [
        'draft' => '#eacaca',
        'planned' => '#cdd3e6',
        'in_progress' => '#f2c6c9',
        'completed' => '#cfe3d8',
        'cancelled' => '#ddd'
    ];
    return $colors[$status] ?? '#eee';
}
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <title>Мои мероприятия - EventDesign</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="css/style.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Segoe UI', Arial, sans-serif;
        }
        
        body.bg-city {
            background: url('images/city.jpg') center/cover no-repeat fixed;
            min-height: 100vh;
            position: relative;
        }
        
        body.bg-city::before {
            content: '';
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(111, 97, 119, 0.85);
            z-index: -1;
        }
        
        .container {
            width: 1200px;
            margin: 0 auto;
            padding: 0 20px;
        }
        
        .header {
            background: #6f6177;
            padding: 20px 0;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        
        .nav {
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .logo {
            font-size: 24px;
            font-weight: bold;
            color: white;
        }
        
        .logo span {
            color: #f5a7ff;
        }
        
        .menu a {
            margin-left: 25px;
            color: white;
            text-decoration: none;
            font-weight: 500;
            transition: color 0.3s;
        }
        
        .menu a:hover {
            color: #f5a7ff;
        }
        
        .auth-buttons {
            display: flex;
            align-items: center;
        }
        
        .btn {
            border: none;
            padding: 10px 20px;
            border-radius: 8px;
            cursor: pointer;
            font-size: 14px;
            font-weight: 500;
            transition: all 0.3s;
        }
        
        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(0,0,0,0.2);
        }
        
        .btn.pink {
            background: #f5a7ff;
            color: #6f6177;
        }
        
        .btn.pink:hover {
            background: #e695f0;
        }
        
        .btn.create {
            background: #f7d0d7;
            color: #6f6177;
            padding: 12px 25px;
            font-size: 16px;
        }
        
        .btn.create:hover {
            background: #f0c0c8;
        }
        
        .next-event-card {
            background: #eee;
            border-radius: 16px;
            padding: 25px;
            margin-bottom: 30px;
            border-left: 5px solid #f5a7ff;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
            transition: all 0.3s;
        }
        
        .next-event-card:hover {
            box-shadow: 0 10px 25px rgba(0,0,0,0.15);
        }
        
        .next-event-card h3 {
            color: #6f6177;
            margin-bottom: 10px;
            font-size: 18px;
        }
        
        .next-event-card h2 {
            color: #333;
            margin-bottom: 10px;
            font-size: 24px;
        }
        
        .next-event-card p {
            color: #666;
            margin: 5px 0;
        }
        
        .dashboard-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
            flex-wrap: wrap;
            gap: 20px;
        }
        
        .dashboard-header h1 {
            color: white;
            font-size: 32px;
            margin: 0;
            text-shadow: 0 2px 4px rgba(0,0,0,0.2);
        }
        
        .filter-bar {
            display: flex;
            gap: 15px;
            margin-bottom: 30px;
            flex-wrap: wrap;
        }
        
        .filter-group {
            display: flex;
            align-items: center;
            gap: 10px;
            background: #eee;
            padding: 10px 15px;
            border-radius: 8px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.05);
        }
        
        .filter-group label {
            color: #6f6177;
            font-weight: bold;
            font-size: 14px;
        }
        
        .filter-group select {
            padding: 8px 12px;
            border: 1px solid #ccc;
            border-radius: 6px;
            background: white;
            font-size: 14px;
            cursor: pointer;
            transition: all 0.3s;
        }
        
        .filter-group select:hover {
            border-color: #f5a7ff;
        }
        
        .filter-group select:focus {
            outline: none;
            border-color: #f5a7ff;
            box-shadow: 0 0 0 3px rgba(245,167,255,0.2);
        }
        
        .event-cards {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
            gap: 30px;
        }
        
        .event-card {
            background: #eee;
            padding: 25px;
            border-radius: 16px;
            cursor: pointer;
            transition: all 0.3s;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
            height: 100%;
            display: flex;
            flex-direction: column;
        }
        
        .event-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 12px 30px rgba(0,0,0,0.2);
        }
        
        .event-header {
            display: flex;
            justify-content: space-between;
            align-items: start;
            margin-bottom: 15px;
        }
        
        .event-title {
            color: #6f6177;
            font-size: 20px;
            margin: 0;
            font-weight: bold;
            line-height: 1.3;
        }
        
        .status-badge {
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 500;
            white-space: nowrap;
        }
        
        .status-draft { background: #eacaca; }
        .status-planned { background: #cdd3e6; }
        .status-in_progress { background: #f2c6c9; }
        .status-completed { background: #cfe3d8; }
        .status-cancelled { background: #ddd; }
        
        .event-photo {
            margin-bottom: 15px;
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        }
        
        .event-photo img {
            width: 100%;
            height: 150px;
            object-fit: cover;
            display: block;
            transition: transform 0.3s;
        }
        
        .event-card:hover .event-photo img {
            transform: scale(1.05);
        }
        
        .no-photo {
            height: 100px;
            background: #ddd;
            border-radius: 12px;
            margin-bottom: 15px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #999;
            font-size: 14px;
            gap: 5px;
        }
        
        .event-info {
            margin: 10px 0;
            color: #555;
        }
        
        .event-info p {
            margin: 8px 0;
            display: flex;
            align-items: center;
            gap: 5px;
        }
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 10px;
            margin: 15px 0;
            padding: 15px 10px;
            background: rgba(111, 97, 119, 0.1);
            border-radius: 12px;
        }
        
        .stat-item {
            text-align: center;
        }
        
        .stat-value {
            font-size: 20px;
            font-weight: bold;
            line-height: 1.2;
        }
        
        .stat-label {
            font-size: 11px;
            color: #666;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .event-description {
            color: #666;
            font-size: 14px;
            margin: 10px 0;
            line-height: 1.5;
            flex-grow: 1;
        }
        
        .actions {
            display: flex;
            gap: 8px;
            margin-top: 15px;
            padding-top: 15px;
            border-top: 1px solid rgba(111, 97, 119, 0.2);
        }
        
        .action-btn {
            flex: 1;
            padding: 8px 12px;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-size: 13px;
            font-weight: 500;
            transition: all 0.3s;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 5px;
        }
        
        .action-btn:hover {
            transform: translateY(-2px);
            filter: brightness(0.95);
        }
        
        .action-btn.edit {
            background: #cdd3e6;
            color: #6f6177;
        }
        
        .action-btn.delete {
            background: #eacaca;
            color: #6f6177;
        }
        
        .action-btn.report {
            background: #cfe3d8;
            color: #6f6177;
        }
        
        .empty-state {
            text-align: center;
            padding: 60px 40px;
            background: #eee;
            border-radius: 20px;
            grid-column: 1/-1;
        }
        
        .empty-state p {
            margin-bottom: 20px;
            font-size: 18px;
            color: #6f6177;
        }
        
        .greeting {
            color: white;
            margin-right: 15px;
            font-size: 14px;
        }
        
        .greeting span {
            color: #f5a7ff;
            font-weight: bold;
        }
        
        @media (max-width: 1200px) {
            .container {
                width: 100%;
            }
        }
        
        @media (max-width: 992px) {
            .dashboard-header h1 {
                font-size: 28px;
            }
        }
        
        @media (max-width: 768px) {
            .nav {
                flex-direction: column;
                gap: 15px;
            }
            
            .menu {
                display: flex;
                flex-wrap: wrap;
                justify-content: center;
                gap: 15px;
            }
            
            .menu a {
                margin: 0;
            }
            
            .filter-bar {
                flex-direction: column;
            }
            
            .filter-group {
                width: 100%;
            }
            
            .filter-group select {
                flex: 1;
            }
            
            .dashboard-header {
                flex-direction: column;
                text-align: center;
            }
            
            .dashboard-header h1 {
                font-size: 26px;
            }
            
            .btn.create {
                width: 100%;
            }
            
            .event-cards {
                grid-template-columns: 1fr;
            }
            
            .actions {
                flex-wrap: wrap;
            }
            
            .action-btn {
                flex: 1 1 calc(50% - 4px);
            }
        }
        
        @media (max-width: 480px) {
            .next-event-card {
                padding: 20px;
            }
            
            .next-event-card h2 {
                font-size: 20px;
            }
            
            .event-card {
                padding: 20px;
            }
            
            .stats-grid {
                padding: 10px 5px;
            }
            
            .stat-value {
                font-size: 16px;
            }
            
            .action-btn {
                flex: 1 1 100%;
            }
        }
        
        @keyframes fadeIn {
            from {
                opacity: 0;
                transform: translateY(20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        .event-card {
            animation: fadeIn 0.5s ease-out;
        }
    </style>
</head>
<body class="bg-city">
    <header class="header">
        <div class="container nav">
            <div class="logo">Event<span>Design</span></div>
            <nav class="menu">
                <a href="index.php">Главная</a>
                <a href="dashboard.php" style="color:#f5a7ff;">Мои мероприятия</a>
                <a href="profile.php">Профиль</a>
            </nav>
            <div class="auth-buttons">
                <span class="greeting">
                    Привет, <span><?php echo htmlspecialchars($user['first_name']); ?></span>!
                </span>
                <button class="btn pink" onclick="logout()">Выход</button>
            </div>
        </div>
    </header>

    <section class="container" style="margin-top: 30px;">
        <!-- Виджет ближайшего мероприятия -->
        <?php if ($next_event): ?>
        <div class="next-event-card">
            <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:20px;">
                <div>
                    <h3>📅 Ближайшее мероприятие</h3>
                    <h2><?php echo htmlspecialchars($next_event['title']); ?></h2>
                    <p>📅 <?php echo formatDate($next_event['event_date']); ?></p>
                    <p>📍 <?php echo htmlspecialchars($next_event['location'] ?? 'Место не указано'); ?></p>
                </div>
                <button class="btn pink" onclick="location.href='event-detail.php?id=<?php echo $next_event['event_id']; ?>'">
                    Открыть
                </button>
            </div>
        </div>
        <?php endif; ?>

        <!-- Заголовок и кнопка создания -->
        <div class="dashboard-header">
            <h1>Мои мероприятия</h1>
            <button class="btn create" onclick="location.href='event-create.php'">
                + Создать мероприятие
            </button>
        </div>

        <!-- Фильтры -->
        <div class="filter-bar">
            <div class="filter-group">
                <label>Фильтр:</label>
                <select id="filterSelect" onchange="applyFilters()">
                    <option value="all" <?php echo $filter == 'all' ? 'selected' : ''; ?>>Все мероприятия</option>
                    <option value="upcoming" <?php echo $filter == 'upcoming' ? 'selected' : ''; ?>>Предстоящие</option>
                    <option value="past" <?php echo $filter == 'past' ? 'selected' : ''; ?>>Прошедшие</option>
                </select>
            </div>
            <div class="filter-group">
                <label>Сортировка:</label>
                <select id="sortSelect" onchange="applyFilters()">
                    <option value="date_desc" <?php echo $sort == 'date_desc' ? 'selected' : ''; ?>>Сначала новые</option>
                    <option value="date_asc" <?php echo $sort == 'date_asc' ? 'selected' : ''; ?>>Сначала старые</option>
                    <option value="name" <?php echo $sort == 'name' ? 'selected' : ''; ?>>По названию</option>
                </select>
            </div>
        </div>

        <!-- Список мероприятий -->
        <?php if (empty($events_with_stats)): ?>
            <div class="empty-state">
                <p>У вас пока нет мероприятий</p>
                <button class="btn create" onclick="location.href='event-create.php'">
                    Создать первое мероприятие
                </button>
            </div>
        <?php else: ?>
            <div class="event-cards">
                <?php foreach ($events_with_stats as $event): ?>
                <div class="event-card" onclick="location.href='event-detail.php?id=<?php echo $event['event_id']; ?>'">
                    
                    <!-- Заголовок и статус -->
                    <div class="event-header">
                        <h3 class="event-title"><?php echo htmlspecialchars($event['title']); ?></h3>
                        <span class="status-badge status-<?php echo $event['status']; ?>">
                            <?php echo getStatusText($event['status']); ?>
                        </span>
                    </div>
                    
                    <!-- Фото мероприятия -->
                    <?php if (!empty($event['photo_path'])): ?>
                        <div class="event-photo">
                            <img src="<?php echo htmlspecialchars($event['photo_path']); ?>" 
                                 alt="Фото мероприятия">
                        </div>
                    <?php else: ?>
                        <div class="no-photo">
                            <span>📷</span> Нет фото
                        </div>
                    <?php endif; ?>
                    
                    <!-- Основная информация -->
                    <div class="event-info">
                        <p>📅 <?php echo formatDate($event['event_date']); ?></p>
                        <p>📍 <?php echo htmlspecialchars($event['location'] ?? 'Место не указано'); ?></p>
                        <p style="font-weight:bold;">
                            💰 <?php echo $event['budget'] ? number_format($event['budget'], 0, ',', ' ') . ' ₽' : 'Бюджет не указан'; ?>
                        </p>
                    </div>
                    
                    <!-- Статистика задач -->
                    <?php if ($event['stats'] && $event['stats']['total_tasks'] > 0): ?>
                    <div class="stats-grid">
                        <div class="stat-item">
                            <div class="stat-value" style="color:#6f6177;">
                                <?php echo $event['stats']['total_tasks']; ?>
                            </div>
                            <div class="stat-label">Всего</div>
                        </div>
                        <div class="stat-item">
                            <div class="stat-value" style="color:#4CAF50;">
                                <?php echo $event['stats']['completed_tasks']; ?>
                            </div>
                            <div class="stat-label">Выполнено</div>
                        </div>
                        <div class="stat-item">
                            <div class="stat-value" style="color:#f5a7ff;">
                                <?php echo number_format($event['stats']['total_cost'] ?? 0, 0, ',', ' '); ?> ₽
                            </div>
                            <div class="stat-label">Расходы</div>
                        </div>
                    </div>
                    <?php endif; ?>
                    
                    <!-- Описание -->
                    <?php if (!empty($event['description'])): ?>
                    <div class="event-description">
                        <?php echo htmlspecialchars(mb_substr($event['description'], 0, 100)) . (mb_strlen($event['description']) > 100 ? '...' : ''); ?>
                    </div>
                    <?php endif; ?>
                    
                    <!-- Кнопки действий -->
                    <div class="actions" onclick="event.stopPropagation()">
                        <button class="action-btn edit" onclick="location.href='event-edit.php?id=<?php echo $event['event_id']; ?>'">
                            ✏️ Редактировать
                        </button>
                        <button class="action-btn delete" onclick="deleteEvent('<?php echo $event['event_id']; ?>', event)">
                            🗑️ Удалить
                        </button>
                        <button class="action-btn report" onclick="location.href='report.php?id=<?php echo $event['event_id']; ?>'">
                            📊 Отчет
                        </button>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </section>

    <script>
        // Функция для применения фильтров
        function applyFilters() {
            const filter = document.getElementById('filterSelect').value;
            const sort = document.getElementById('sortSelect').value;
            window.location.href = `dashboard.php?filter=${filter}&sort=${sort}`;
        }

        // Функция удаления мероприятия
        async function deleteEvent(id, event) {
            event.stopPropagation();
            
            if (!confirm('Вы уверены, что хотите удалить это мероприятие? Все задачи также будут удалены.')) {
                return;
            }

            try {
                const formData = new FormData();
                formData.append('id', id);
                
                const response = await fetch('delete-event.php', {
                    method: 'POST',
                    body: formData
                });
                
                const data = await response.json();
                
                if (data.success) {
                    alert('Мероприятие успешно удалено');
                    window.location.reload();
                } else {
                    alert('Ошибка при удалении: ' + (data.message || 'Неизвестная ошибка'));
                }
            } catch (error) {
                console.error('Delete error:', error);
                alert('Ошибка при удалении');
            }
        }

        // Функция выхода
        function logout() {
            if (confirm('Выйти из аккаунта?')) {
                window.location.href = 'logout.php';
            }
        }

        // Добавляем эффект загрузки при клике
        document.querySelectorAll('.event-card').forEach(card => {
            card.addEventListener('click', function() {
                // Можно добавить анимацию загрузки
                this.style.opacity = '0.8';
            });
        });
    </script>
</body>
</html>