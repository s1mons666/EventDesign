<?php
// public/event-detail.php
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

$event_id = $_GET['id'] ?? '';
if (!$event_id) {
    header('Location: dashboard.php');
    exit;
}

// Получаем данные мероприятия
$stmt = $conn->prepare("SELECT * FROM events WHERE event_id = ? AND user_id = ?");
$stmt->execute([$event_id, $user_id]);
$event = $stmt->fetch();

if (!$event) {
    header('Location: dashboard.php');
    exit;
}

// Получаем задачи
$stmt = $conn->prepare("SELECT * FROM tasks WHERE event_id = ? ORDER BY created_at");
$stmt->execute([$event_id]);
$tasks = $stmt->fetchAll();

// Получаем данные пользователя
$stmt = $conn->prepare("SELECT * FROM users WHERE user_id = ?");
$stmt->execute([$user_id]);
$user = $stmt->fetch();

// Подсчет статистики
$total_cost = 0;
$completed_count = 0;
foreach ($tasks as $task) {
    $total_cost += $task['cost'];
    if ($task['is_completed']) {
        $completed_count++;
    }
}

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
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <title><?php echo htmlspecialchars($event['title']); ?> - EventDesign</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="css/style.css">
    <style>
        .event-photo {
            margin-bottom: 20px;
            text-align: center;
        }
        
        .event-photo img {
            max-width: 100%;
            max-height: 400px;
            border-radius: 16px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.2);
        }
        
        .no-photo {
            height: 200px;
            background: #ddd;
            border-radius: 16px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #999;
            font-size: 18px;
            margin-bottom: 20px;
        }
        
        .task-item {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 12px;
            border-bottom: 1px solid #ddd;
        }
        
        .task-item.completed span {
            text-decoration: line-through;
            color: #999;
        }
        
        .task-item input[type="checkbox"] {
            width: 18px;
            height: 18px;
            cursor: pointer;
        }
        
        .delete-task {
            background: none;
            border: none;
            color: #eacaca;
            cursor: pointer;
            font-size: 18px;
            transition: color 0.3s;
        }
        
        .delete-task:hover {
            color: #d4a5a5;
        }
        
        .stats-card {
            background: white;
            padding: 20px;
            border-radius: 12px;
            text-align: center;
        }
        
        .stats-number {
            font-size: 32px;
            font-weight: bold;
            color: #6f6177;
        }
        
        .back-button {
            background: white;
            color: #6f6177;
            padding: 10px 20px;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            transition: all 0.3s;
        }
        
        .back-button:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
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
                <a href="profile.php">Профиль</a>
            </nav>
            <div class="auth-buttons">
                <span style="color:white; margin-right:15px;">
                    <?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name']); ?>
                </span>
                <button class="btn pink" onclick="logout()">Выход</button>
            </div>
        </div>
    </header>

    <section class="container" style="margin-top: 30px;">
        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:20px; flex-wrap:wrap; gap:15px;">
            <h1 style="color:white;"><?php echo htmlspecialchars($event['title']); ?></h1>
            <div style="display:flex; gap:10px;">
                <button class="btn edit" onclick="location.href='event-edit.php?id=<?php echo $event_id; ?>'">✏️ Редактировать</button>
                <button class="btn report" onclick="location.href='report.php?id=<?php echo $event_id; ?>'">📊 Отчет</button>
                <button class="back-button" onclick="location.href='dashboard.php'">← Назад</button>
            </div>
        </div>

        <!-- Фото мероприятия -->
        <?php if (!empty($event['photo_path'])): ?>
            <div class="event-photo">
                <img src="<?php echo htmlspecialchars($event['photo_path']); ?>" alt="Фото мероприятия">
            </div>
        <?php else: ?>
            <div class="no-photo">
                📷 Фото не загружено
            </div>
        <?php endif; ?>

        <!-- Информация о мероприятии -->
        <div style="background:#eee; padding:25px; border-radius:16px; margin-bottom:30px;">
            <div style="display:grid; grid-template-columns:repeat(auto-fit, minmax(200px, 1fr)); gap:20px;">
                <div>
                    <p style="color:#666; margin-bottom:5px;">📅 Дата проведения</p>
                    <p style="font-weight:bold;"><?php echo formatDate($event['event_date']); ?></p>
                </div>
                <div>
                    <p style="color:#666; margin-bottom:5px;">📍 Место</p>
                    <p style="font-weight:bold;"><?php echo htmlspecialchars($event['location'] ?? 'Не указано'); ?></p>
                </div>
                <div>
                    <p style="color:#666; margin-bottom:5px;">💰 Бюджет</p>
                    <p style="font-weight:bold;"><?php echo $event['budget'] ? number_format($event['budget'], 0, ',', ' ') . ' ₽' : 'Не указан'; ?></p>
                </div>
                <div>
                    <p style="color:#666; margin-bottom:5px;">📊 Статус</p>
                    <p><span style="padding:4px 12px; border-radius:20px; background:<?php 
                        echo $event['status'] == 'draft' ? '#eacaca' : 
                            ($event['status'] == 'planned' ? '#cdd3e6' : 
                            ($event['status'] == 'in_progress' ? '#f2c6c9' : '#cfe3d8')); ?>;">
                        <?php echo getStatusText($event['status']); ?>
                    </span></p>
                </div>
            </div>
            <?php if (!empty($event['description'])): ?>
            <div style="margin-top:20px;">
                <p style="color:#666; margin-bottom:5px;">📝 Описание</p>
                <p style="white-space: pre-line;"><?php echo nl2br(htmlspecialchars($event['description'])); ?></p>
            </div>
            <?php endif; ?>
        </div>

        <div style="display:grid; grid-template-columns:2fr 1fr; gap:30px;">
            <!-- Задачи -->
            <div style="background:#eee; padding:25px; border-radius:16px;">
                <h2 style="margin-bottom:20px;">Задачи и расходы</h2>
                
                <div style="display:flex; gap:10px; margin-bottom:20px;">
                    <input type="text" id="taskName" class="input" placeholder="Название задачи" style="flex:2;">
                    <input type="number" id="taskCost" class="input" placeholder="Стоимость" style="flex:1;" min="0" step="100">
                    <button class="btn add" id="addTaskBtn" style="padding:12px 20px;">+ Добавить</button>
                </div>
                
                <div id="tasksList" style="margin-bottom:20px;">
                    <?php if (empty($tasks)): ?>
                        <p style="color:#999; text-align:center;">Нет задач. Добавьте первую задачу!</p>
                    <?php else: ?>
                        <?php foreach ($tasks as $task): ?>
                        <div class="task-item <?php echo $task['is_completed'] ? 'completed' : ''; ?>">
                            <div style="display:flex; align-items:center; gap:10px;">
                                <input type="checkbox" <?php echo $task['is_completed'] ? 'checked' : ''; ?> 
                                       onchange="toggleTask('<?php echo $task['task_id']; ?>', this.checked)">
                                <span><?php echo htmlspecialchars($task['title']); ?></span>
                            </div>
                            <div style="display:flex; align-items:center; gap:15px;">
                                <span style="font-weight:bold;"><?php echo number_format($task['cost'], 0, ',', ' '); ?> ₽</span>
                                <button class="delete-task" onclick="deleteTask('<?php echo $task['task_id']; ?>')">✕</button>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
                
                <div id="totalAmount" style="font-size:18px; font-weight:bold; padding-top:15px; border-top:2px solid #ddd;">
                    Общая сумма: <?php echo number_format($total_cost, 0, ',', ' '); ?> ₽
                </div>
            </div>

            <!-- Статистика -->
            <div style="background:#eee; padding:25px; border-radius:16px;">
                <h2 style="margin-bottom:20px;">Статистика</h2>
                <div class="stats-card" style="margin-bottom:15px;">
                    <div class="stats-number"><?php echo $completed_count; ?></div>
                    <div style="color:#666;">Выполнено задач</div>
                </div>
                <div class="stats-card" style="margin-bottom:15px;">
                    <div class="stats-number"><?php echo count($tasks) - $completed_count; ?></div>
                    <div style="color:#666;">Осталось задач</div>
                </div>
                <div class="stats-card" style="margin-bottom:15px;">
                    <div class="stats-number" style="color:#f5a7ff;"><?php echo number_format($total_cost, 0, ',', ' '); ?> ₽</div>
                    <div style="color:#666;">Всего расходов</div>
                </div>
                <hr style="margin:20px 0;">
                <button class="btn report" onclick="location.href='report.php?id=<?php echo $event_id; ?>'" style="width:100%; padding:12px;">
                    📊 Сформировать отчет
                </button>
            </div>
        </div>
    </section>

    <script>
        const eventId = '<?php echo $event_id; ?>';
        const authToken = '<?php echo $_COOKIE['auth_token'] ?? ''; ?>';

        async function addTask() {
            const name = document.getElementById('taskName')?.value;
            const cost = document.getElementById('taskCost')?.value;

            if (!name) {
                alert('Введите название задачи');
                return;
            }

            try {
                const formData = new FormData();
                formData.append('event_id', eventId);
                formData.append('title', name);
                formData.append('cost', cost || '0');
                formData.append('is_completed', '0');

                const response = await fetch('create-task-handler.php', {
                    method: 'POST',
                    body: formData
                });

                const data = await response.json();
                
                if (data.success) {
                    document.getElementById('taskName').value = '';
                    document.getElementById('taskCost').value = '';
                    window.location.reload();
                } else {
                    alert('Ошибка при добавлении: ' + (data.message || 'Неизвестная ошибка'));
                }
            } catch (error) {
                console.error('Error adding task:', error);
                alert('Ошибка при добавлении задачи');
            }
        }

        async function toggleTask(taskId, completed) {
            try {
                const formData = new FormData();
                formData.append('id', taskId);
                formData.append('is_completed', completed ? '1' : '0');

                const response = await fetch('update-task-handler.php', {
                    method: 'POST',
                    body: formData
                });

                const data = await response.json();
                
                if (data.success) {
                    window.location.reload();
                }
            } catch (error) {
                console.error('Error toggling task:', error);
            }
        }

        async function deleteTask(taskId) {
            if (!confirm('Удалить задачу?')) return;

            try {
                const formData = new FormData();
                formData.append('id', taskId);

                const response = await fetch('delete-task-handler.php', {
                    method: 'POST',
                    body: formData
                });

                const data = await response.json();
                
                if (data.success) {
                    window.location.reload();
                } else {
                    alert('Ошибка при удалении');
                }
            } catch (error) {
                console.error('Error deleting task:', error);
                alert('Ошибка при удалении');
            }
        }

        document.getElementById('addTaskBtn')?.addEventListener('click', addTask);

        document.getElementById('taskName')?.addEventListener('keypress', function(e) {
            if (e.key === 'Enter') {
                addTask();
            }
        });

        function logout() {
            if (confirm('Выйти из аккаунта?')) {
                window.location.href = 'logout.php';
            }
        }
    </script>
</body>
</html>